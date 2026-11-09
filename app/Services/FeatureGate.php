<?php

namespace App\Services;

use App\Models\Feature;
use App\Models\HostFeature;
use App\Models\User;

class FeatureGate
{
    public static function enabled(User $owner, string $key): bool
    {
        $feature = Feature::query()->where('key', $key)->first();
        if (! $feature || ! $feature->active) {
            return false;
        }

        $now = now();
        $hostFeature = HostFeature::query()
            ->where('user_id', $owner->getKey())
            ->where('feature_key', $key)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->first();

        if ($feature->is_free) {
            return $hostFeature?->enabled ?? true;
        }

        return (bool) $hostFeature?->enabled;
    }
}
