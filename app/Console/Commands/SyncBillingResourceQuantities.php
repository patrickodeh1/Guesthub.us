<?php

namespace App\Console\Commands;

use App\Models\HostPackage;
use App\Services\Billing\BillingQuantityResolver;
use App\Services\Billing\BillingService;
use App\Services\Billing\PlanPricing;
use App\Services\Billing\SubscriptionSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncBillingResourceQuantities extends Command
{
    protected $signature = 'billing:sync-resource-quantities';

    protected $description = 'Reconcile per-property and per-device subscription quantities with live host resources.';

    public function handle(BillingService $billing, BillingQuantityResolver $resolver): int
    {
        $updated = 0;
        $unchanged = 0;
        $failed = 0;

        HostPackage::query()
            ->where('billing_source', 'stripe')
            ->whereNotNull('stripe_subscription_id')
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->with(['package', 'user'])
            ->chunkById(50, function ($plans) use ($billing, $resolver, &$updated, &$unchanged, &$failed) {
                foreach ($plans as $plan) {
                    if (! $plan->user || ! $plan->package) {
                        $unchanged++;
                        continue;
                    }

                    $existing = DB::table('host_features')
                        ->where('user_id', $plan->user_id)
                        ->where('source', 'stripe_addon')
                        ->pluck('quantity', 'feature_key')
                        ->map(fn ($quantity) => (int) $quantity)
                        ->all();

                    $selected = array_fill_keys(array_keys($existing), 1);
                    $selected = array_diff_key($selected, array_flip($plan->package->allFeatureKeys()));
                    $resolved = $resolver->resolve($plan->user, $selected);
                    ksort($existing);
                    ksort($resolved);

                    if ($existing === $resolved) {
                        $unchanged++;
                        continue;
                    }

                    $quote = PlanPricing::quote($plan->package, $resolved);
                    if ($quote['total_cents'] <= 0) {
                        Log::warning('Billing quantity sync skipped a zero-value subscription', [
                            'host_package_id' => $plan->id,
                            'user_id' => $plan->user_id,
                        ]);
                        $unchanged++;
                        continue;
                    }

                    try {
                        $subscription = $billing->changeAddons($plan, $quote, $plan->package, $resolved);
                        if (! empty($subscription->pending_update)) {
                            throw new \RuntimeException('Stripe returned a pending subscription update.');
                        }
                        SubscriptionSync::handle($subscription);
                        $updated++;
                    } catch (Throwable $exception) {
                        $failed++;
                        report($exception);
                        Log::error('Could not reconcile Stripe resource quantities', [
                            'host_package_id' => $plan->id,
                            'user_id' => $plan->user_id,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Updated: {$updated}; unchanged: {$unchanged}; failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
