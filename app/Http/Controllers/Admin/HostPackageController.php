<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\HostPackage;
use App\Models\Package;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HostPackageController extends Controller
{
    private function adminOnly(Request $request): void
    {
        abort_unless($request->user()->hasRole('admin'), 403);
    }

    public function index(Request $request)
    {
        $this->adminOnly($request);
        $q = trim((string) $request->query('q', ''));
        $users = User::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();
        $plans = HostPackage::query()->whereIn('user_id', $users->pluck('id'))->with('package')->get()->keyBy('user_id');

        return view('admin.host-packages.index', ['users' => $users, 'plans' => $plans, 'q' => $q]);
    }

    public function edit(Request $request, User $user)
    {
        $this->adminOnly($request);
        $features = Feature::query()->where('is_free', false)->orderBy('name')->get();
        $hostPackage = HostPackage::query()->where('user_id', $user->id)->with('package.parent')->first();
        $included = $hostPackage && $hostPackage->package && $hostPackage->isGranting() ? $hostPackage->package->allFeatureKeys() : [];
        $grants = DB::table('host_features')->where('user_id', $user->id)->get()->keyBy('feature_key');

        $estimate = $hostPackage && $hostPackage->package && in_array($hostPackage->status, ['active', 'trialing'], true) ? (int) $hostPackage->package->price_cents : 0;
        foreach ($features as $f) {
            $g = $grants->get($f->key);
            if ($g && $g->enabled && ! in_array($f->key, $included, true)) {
                $units = ($f->billing_unit ?: 'flat') === 'flat' ? 1 : max(1, (int) $g->quantity);
                $estimate += (int) $f->price_cents * $units;
            }
        }

        return view('admin.host-packages.edit', [
            'user' => $user,
            'packages' => Package::query()->where('active', true)->orderBy('sort_order')->get(),
            'features' => $features,
            'hostPackage' => $hostPackage,
            'included' => $included,
            'grants' => $grants,
            'estimate' => $estimate,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->adminOnly($request);
        $features = Feature::query()->where('is_free', false)->get()->keyBy('key');
        $data = $request->validate([
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'status' => ['required', 'in:active,trialing,canceled'],
            'trial_ends_at' => ['nullable', 'date'],
            'addons' => ['nullable', 'array'],
            'addons.*.enabled' => ['nullable', 'boolean'],
            'addons.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $package = ! empty($data['package_id']) ? Package::query()->find($data['package_id']) : null;
        $trialEnds = null;
        if ($package && $data['status'] === 'trialing') {
            if (! empty($data['trial_ends_at'])) {
                $trialEnds = Carbon::parse($data['trial_ends_at'])->endOfDay();
            } elseif ($package->trial_days) {
                $trialEnds = now()->addDays((int) $package->trial_days);
            } else {
                throw ValidationException::withMessages(['trial_ends_at' => 'Pick a trial end date, or set trial days on the package.']);
            }
        }

        DB::transaction(function () use ($user, $package, $data, $features, $trialEnds) {
            if (! $package) {
                HostPackage::query()->where('user_id', $user->id)->delete();
            } else {
                $hp = HostPackage::query()->firstOrNew(['user_id' => $user->id]);
                $hp->package_id = $package->id;
                $hp->status = $data['status'];
                $hp->trial_ends_at = $trialEnds;
                if (! $hp->started_at) {
                    $hp->started_at = now();
                }
                $hp->save();
            }

            $included = ($package && in_array($data['status'], ['active', 'trialing'], true)) ? $package->allFeatureKeys() : [];
            foreach ($features as $key => $feature) {
                if (in_array($key, $included, true)) {
                    continue;
                }
                $row = $data['addons'][$key] ?? [];
                $on = ! empty($row['enabled']);
                $exists = DB::table('host_features')->where('user_id', $user->id)->where('feature_key', $key)->exists();
                if ($on) {
                    $qty = max(1, (int) ($row['quantity'] ?? 1));
                    if ($exists) {
                        DB::table('host_features')->where('user_id', $user->id)->where('feature_key', $key)
                            ->update(['enabled' => 1, 'quantity' => $qty, 'updated_at' => now()]);
                    } else {
                        DB::table('host_features')->insert([
                            'user_id' => $user->id, 'feature_key' => $key, 'enabled' => 1, 'quantity' => $qty,
                            'source' => 'addon', 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                } elseif ($exists) {
                    DB::table('host_features')->where('user_id', $user->id)->where('feature_key', $key)->delete();
                }
            }
        });

        ActivityLogService::admin('host_package_updated', "Package and add-ons updated for {$user->name}.", 'settings', [
            'subject_type' => User::class, 'subject_id' => $user->id,
        ]);

        return redirect()->route('admin.host-packages.edit', $user)->with('success', 'Saved.');
    }
}
