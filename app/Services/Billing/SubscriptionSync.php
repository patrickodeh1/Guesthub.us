<?php

namespace App\Services\Billing;

use App\Models\Feature;
use App\Models\HostPackage;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionSync
{
    public const EVENTS = [
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
    ];

    /** @param \Stripe\Subscription|object $sub */
    public static function handle($sub): void
    {
        $meta = $sub->metadata ? $sub->metadata->toArray() : [];
        $subId = (string) $sub->id;

        $hp = HostPackage::query()->where('stripe_subscription_id', $subId)->first();
        $userId = $hp ? (int) $hp->user_id : (int) ($meta['user_id'] ?? 0);
        if (! $userId || ! User::query()->whereKey($userId)->exists()) {
            Log::warning('Subscription sync: no matching user', ['subscription' => $subId]);

            return;
        }
        $hp ??= HostPackage::query()->where('user_id', $userId)->first();

        if ($hp && $hp->billing_source === 'admin') {
            Log::info('Subscription sync: admin-managed plan left alone', ['user_id' => $userId]);

            return;
        }
        if ($hp && $hp->stripe_subscription_id && $hp->stripe_subscription_id !== $subId
            && in_array($hp->status, ['active', 'trialing', 'past_due'], true)) {
            Log::info('Subscription sync: event for an older subscription ignored', ['user_id' => $userId, 'subscription' => $subId]);

            return;
        }

        $status = match ($sub->status) {
            'trialing' => 'trialing',
            'active' => 'active',
            'past_due', 'unpaid' => 'past_due',
            'canceled', 'incomplete_expired', 'paused' => 'canceled',
            default => null,
        };
        if ($status === null) {
            return;
        }

        $package = Package::query()->find((int) ($meta['package_id'] ?? 0));
        if (! $package && ! $hp) {
            Log::warning('Subscription sync: no package for new plan', ['subscription' => $subId]);

            return;
        }

        $periodEnd = $sub->current_period_end ?? ($sub->items->data[0]->current_period_end ?? null);
        $trialEnd = $sub->trial_end ?? null;

        DB::transaction(function () use ($hp, $userId, $package, $status, $sub, $subId, $periodEnd, $trialEnd, $meta) {
            $hp ??= new HostPackage(['user_id' => $userId]);
            $hp->package_id = $package?->id ?? $hp->package_id;
            $hp->status = $status;
            if ($trialEnd) {
                $hp->trial_ends_at = Carbon::createFromTimestamp((int) $trialEnd);
            }
            $hp->current_period_end = $periodEnd ? Carbon::createFromTimestamp((int) $periodEnd) : $hp->current_period_end;
            $hp->cancel_at_period_end = (bool) (($sub->cancel_at_period_end ?? false) || ($sub->cancel_at ?? null));
            $hp->past_due_since = $status === 'past_due' ? ($hp->past_due_since ?? now()) : null;
            $hp->billing_source = 'stripe';
            $hp->stripe_customer_id = is_string($sub->customer) ? $sub->customer : ($sub->customer->id ?? $hp->stripe_customer_id);
            $hp->stripe_subscription_id = $subId;
            $hp->started_at ??= now();
            $hp->save();

            self::syncAddons($userId, $status, (array) json_decode((string) ($meta['addons'] ?? '{}'), true));
        });
    }

    /** Only touches rows whose source is stripe_addon. */
    private static function syncAddons(int $userId, string $status, array $wanted): void
    {
        $live = in_array($status, ['active', 'trialing', 'past_due'], true);
        if (! $live) {
            $wanted = [];
        }

        $valid = Feature::query()->where('is_free', false)->whereIn('key', array_keys($wanted))->pluck('key')->all();

        foreach ($valid as $key) {
            $row = DB::table('host_features')->where('user_id', $userId)->where('feature_key', $key)->first();
            if ($row && $row->source !== 'stripe_addon') {
                continue;
            }
            $qty = max(0, (int) $wanted[$key]);
            if ($row) {
                DB::table('host_features')->where('id', $row->id)->update(['enabled' => $qty > 0 ? 1 : 0, 'quantity' => $qty, 'updated_at' => now()]);
            } else {
                DB::table('host_features')->insert([
                    'user_id' => $userId, 'feature_key' => $key, 'enabled' => $qty > 0 ? 1 : 0, 'quantity' => $qty,
                    'source' => 'stripe_addon', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        DB::table('host_features')->where('user_id', $userId)->where('source', 'stripe_addon')
            ->whereNotIn('feature_key', $valid ?: [''])->delete();
    }
}
