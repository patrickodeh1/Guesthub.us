<?php

namespace App\Services;

use App\Models\Feature;
use App\Models\HostFeature;
use App\Models\HostPackage;
use App\Models\User;

class FeatureGate
{
    public static function enabled(User $owner, string $key): bool
    {
        $feature = Feature::query()->where('key', $key)->first();
        if (! $feature || ! $feature->active) {
            return false;
        }

        // The platform admin's own properties are never plan-gated.
        if ($owner->role === 'admin') {
            return true;
        }

        $now = now();
        $hostFeature = HostFeature::query()
            ->where('user_id', $owner->getKey())
            ->where('feature_key', $key)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->first();

        // An explicit grant for this host always wins, on or off.
        // Add-ons bought through Stripe only count while the plan is granting.
        if ($hostFeature) {
            if ($hostFeature->source !== 'stripe_addon') {
                return (bool) $hostFeature->enabled;
            }
            if ($hostFeature->enabled && self::planGranting($owner)) {
                return true;
            }
            // Lapsed add-on: fall through to the free and package rules.
        }

        if ($feature->is_free) {
            return true;
        }

        return self::inPackage($owner, $key);
    }

    private static function planGranting(User $owner): bool
    {
        $hostPackage = HostPackage::query()->where('user_id', $owner->getKey())->first();

        return (bool) ($hostPackage && $hostPackage->isGranting());
    }

    private static function inPackage(User $owner, string $key): bool
    {
        $hostPackage = HostPackage::query()->where('user_id', $owner->getKey())->with('package.parent')->first();
        if (! $hostPackage || ! $hostPackage->isGranting() || ! $hostPackage->package || ! $hostPackage->package->active) {
            return false;
        }

        return in_array($key, $hostPackage->package->allFeatureKeys(), true);
    }
}
