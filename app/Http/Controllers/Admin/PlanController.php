<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\HostPackage;
use App\Models\Package;
use App\Services\Billing\BillingService;
use App\Services\Billing\BillingQuantityResolver;
use App\Models\User;
use App\Services\Billing\PlanPricing;
use Illuminate\Http\Request;

class PlanController extends Controller
{

    /**
     * Accept selected feature keys only. Billable quantities are calculated
     * server-side from active properties and registered smart-lock devices.
     *
     * @param array<int,string> $excluded
     * @return array<string,int>
     */
    private function ensureRecurringLine(Package $package, array $quote): array
    {
        if ($quote['lines'] === [] && (int) $package->price_cents <= 0) {
            $quote['lines'][] = [
                'type' => 'package',
                'key' => $package->key,
                'name' => $package->name,
                'unit_cents' => 0,
                'quantity' => 1,
            ];
        }

        return $quote;
    }

    private function selectedAddons(Request $request, User $user, array $excluded = []): array
    {
        $request->validate([
            'addons' => ['nullable', 'array'],
            'addons.*' => ['array'],
            'addons.*.enabled' => ['nullable', 'boolean'],
            'addons.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $selected = [];
        foreach ((array) $request->input('addons', []) as $key => $row) {
            if (! empty($row['enabled']) && ! in_array($key, $excluded, true)) {
                $selected[$key] = 1;
            }
        }

        return app(BillingQuantityResolver::class)->resolve($user, $selected);
    }

    public function checkout(Request $request, BillingService $billing)
    {
        $user = $request->user();
        $package = Package::query()->where('active', true)->find((int) $request->input('package'));
        if (! $package) {
            return back()->withErrors(['package' => 'Pick a package first.']);
        }

        $existing = HostPackage::query()->where('user_id', $user->id)->first();
        if ($existing && $existing->billing_source === 'admin') {
            return back()->withErrors(['package' => 'Your plan is managed by your account admin.']);
        }
        if ($existing && $existing->stripe_subscription_id && in_array($existing->status, ['active', 'trialing', 'past_due'], true)) {
            return back()->withErrors(['package' => 'You already have a subscription. Use Manage billing to change or cancel it.']);
        }

        $addons = $this->selectedAddons($request, $request->user());
        $quote = PlanPricing::quote($package, $addons);

        if ($quote['total_cents'] <= 0) {
            HostPackage::query()->updateOrCreate(['user_id' => $user->id], [
                'package_id' => $package->id,
                'status' => 'active',
                'trial_ends_at' => null,
                'started_at' => $existing?->started_at ?? now(),
                'billing_source' => 'self',
            ]);

            return redirect()->route('admin.plan.index')->with('success', 'Plan saved.');
        }

        try {
            $url = $billing->checkoutUrl(
                $user, $package, $quote, $addons, $existing,
                route('admin.plan.index', ['checkout' => 'done']),
                route('admin.plan.index', ['checkout' => 'cancelled']),
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['package' => 'Could not start checkout. Please try again.']);
        }

        return redirect()->away($url);
    }

    /** @return array{error?:string, package?:Package, addons?:array, quote?:array, existing?:?HostPackage} */
    private function prepare(Request $request): array
    {
        $user = $request->user();
        $package = Package::query()->where('active', true)->find((int) $request->input('package'));
        if (! $package) {
            return ['error' => 'Pick a package first.'];
        }
        $existing = HostPackage::query()->where('user_id', $user->id)->first();
        if ($existing && $existing->billing_source === 'admin') {
            return ['error' => 'Your plan is managed by your account admin.'];
        }
        if ($existing && $existing->stripe_subscription_id && in_array($existing->status, ['active', 'trialing', 'past_due'], true)) {
            return ['error' => 'You already have a subscription. Use Manage billing to change or cancel it.'];
        }
        $addons = $this->selectedAddons($request, $request->user());
        $quote = PlanPricing::quote($package, $addons);
        if ($quote['total_cents'] <= 0) {
            return ['error' => 'Nothing to charge for this selection.'];
        }

        return compact('package', 'addons', 'quote', 'existing');
    }

    public function setup(Request $request, BillingService $billing)
    {
        $d = $this->prepare($request);
        if (isset($d['error'])) {
            return response()->json(['error' => $d['error']], 422);
        }
        try {
            return response()->json(['client_secret' => $billing->setupIntent($request->user(), $d['existing'])]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'Could not start card setup. Please try again.'], 422);
        }
    }

    public function subscribe(Request $request, BillingService $billing)
    {
        $d = $this->prepare($request);
        if (isset($d['error'])) {
            return response()->json(['error' => $d['error']], 422);
        }
        try {
            $sub = $billing->subscribe($request->user(), $d['package'], $d['quote'], $d['addons'], $d['existing'], (string) $request->input('setup_intent_id'));
            \App\Services\Billing\SubscriptionSync::handle($sub);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'Your card could not be set up for this plan. Please try another card.'], 422);
        }

        return response()->json(['redirect' => route('admin.plan.index', ['checkout' => 'done'])]);
    }

    public function changeAddons(Request $request, BillingService $billing)
    {
        $hp = HostPackage::query()->where('user_id', $request->user()->id)->with('package')->first();
        if (! $hp || $hp->billing_source !== 'stripe' || ! $hp->stripe_subscription_id || ! $hp->package
            || ! in_array($hp->status, ['active', 'trialing', 'past_due'], true)) {
            return back()->withErrors(['package' => 'There is no active subscription to change.']);
        }

        $addons = $this->selectedAddons($request, $request->user(), $hp->package->allFeatureKeys());
        $quote = $this->ensureRecurringLine($hp->package, PlanPricing::quote($hp->package, $addons));
        if ($quote['lines'] === []) {
            return back()->withErrors(['package' => 'Nothing to bill for this selection. Use Manage billing to cancel.']);
        }

        try {
            $sub = $billing->changeAddons($hp, $quote, $hp->package, $addons);
            if (! empty($sub->pending_update)) {
                return back()->withErrors(['package' => 'Your payment did not go through, so your add-ons were not changed. Update your card under Manage billing and try again.']);
            }
            \App\Services\Billing\SubscriptionSync::handle($sub);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['package' => 'Could not update your add-ons. Please try again.']);
        }

        return redirect()->route('admin.plan.index')->with('success', 'Add-ons updated.');
    }

    public function switchPlan(Request $request, BillingService $billing)
    {
        $hp = HostPackage::query()->where('user_id', $request->user()->id)->with('package')->first();
        if (! $hp || $hp->billing_source !== 'stripe' || ! $hp->stripe_subscription_id
            || ! in_array($hp->status, ['active', 'trialing', 'past_due'], true)) {
            return back()->withErrors(['package' => 'There is no active subscription to change.']);
        }
        $package = Package::query()->where('active', true)->find((int) $request->input('package'));
        if (! $package) {
            return back()->withErrors(['package' => 'Pick a package first.']);
        }
        if ($package->id === $hp->package_id) {
            return back()->withErrors(['package' => 'You are already on this plan.']);
        }

        $included = $package->allFeatureKeys();
        $addons = $this->selectedAddons($request, $request->user(), $included);
        $quote = $this->ensureRecurringLine($package, PlanPricing::quote($package, $addons));
        if ($quote['lines'] === []) {
            return back()->withErrors(['package' => 'Nothing to bill for this selection. Use Manage billing to cancel.']);
        }

        try {
            $sub = $billing->changeAddons($hp, $quote, $package, $addons);
            if (! empty($sub->pending_update)) {
                return back()->withErrors(['package' => 'Your payment did not go through, so your plan was not changed. Update your card under Manage billing and try again.']);
            }
            \App\Services\Billing\SubscriptionSync::handle($sub);
            $hp->forceFill(['package_id' => $package->id])->save();
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['package' => 'Could not change your plan. Please try again.']);
        }

        return redirect()->route('admin.plan.index')->with('success', 'Plan changed to '.$package->name.'.');
    }

    public function portal(Request $request, BillingService $billing)
    {
        $hp = HostPackage::query()->where('user_id', $request->user()->id)->first();
        if (! $hp || $hp->billing_source !== 'stripe' || ! $hp->stripe_customer_id) {
            return back()->withErrors(['package' => 'There is no billing account to manage yet.']);
        }

        try {
            return redirect()->away($billing->portalUrl($hp->stripe_customer_id, route('admin.plan.index')));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['package' => 'Could not open billing. Please try again.']);
        }
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'owner', 'company']), 403);

        $current = HostPackage::query()->where('user_id', $request->user()->id)->with('package')->first();
        $packages = Package::query()->where('active', true)->orderBy('sort_order')->get();
        $addonFeatures = Feature::query()->where('is_free', false)->where('active', true)->where('price_cents', '>', 0)->orderBy('name')->get();

        $selected = $packages->firstWhere('id', (int) $request->query('package', $current?->package_id));
        $addons = ($request->has('package') || ! $current || $current->billing_source !== 'stripe')
            ? []
            : \Illuminate\Support\Facades\DB::table('host_features')
                ->where('user_id', $request->user()->id)->where('source', 'stripe_addon')
                ->pluck('quantity', 'feature_key')->map(fn ($q) => (int) $q)->all();
        foreach ((array) $request->query('addons', []) as $key => $row) {
            if (! empty($row['enabled'])) {
                $addons[$key] = 1;
            }
        }
        $quantityResolver = app(BillingQuantityResolver::class);
        $billingQuantities = $quantityResolver->counts($request->user());
        $addons = $quantityResolver->resolve($request->user(), $addons);

        return view('admin.plan.index', [
            'current' => $current,
            'packages' => $packages,
            'addonFeatures' => $addonFeatures,
            'selected' => $selected,
            'addons' => $addons,
            'billingQuantities' => $billingQuantities,
            'quote' => PlanPricing::quote($selected, $addons),
            'keysByPackage' => $packages->mapWithKeys(fn ($p) => [$p->id => $p->allFeatureKeys()]),
            'compareFeatures' => Feature::query()->whereIn('key', $packages->flatMap(fn ($p) => $p->allFeatureKeys())->unique()->values()->all())->orderBy('name')->get(),
        ]);
    }
}
