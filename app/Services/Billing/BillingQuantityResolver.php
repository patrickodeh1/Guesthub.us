<?php

namespace App\Services\Billing;

use App\Models\Feature;
use App\Models\Property;
use App\Models\PropertyLock;
use App\Models\User;

class BillingQuantityResolver
{
    /**
     * Resolve subscription add-on quantities from billable resources owned or
     * managed by the host. Smart locks are the current per-device resource.
     *
     * @param array<string,int> $selected Feature keys selected by the host
     * @return array<string,int> Selected feature keys with their billable quantity
     */
    public function resolve(User $user, array $selected): array
    {
        if ($selected === []) {
            return [];
        }

        $counts = $this->counts($user);
        $features = Feature::query()
            ->where('active', true)
            ->where('is_free', false)
            ->whereIn('key', array_keys($selected))
            ->get()
            ->keyBy('key');

        $resolved = [];
        foreach ($selected as $key => $_quantity) {
            $feature = $features->get($key);
            if (! $feature) {
                continue;
            }

            $unit = $feature->billing_unit ?: 'flat';
            $quantity = match ($unit) {
                'per_property' => $counts['per_property'],
                'per_device' => $counts['per_device'],
                default => 1,
            };

            // Do not create a paid line for a resource the host does not have.
            if ($quantity > 0) {
                $resolved[$key] = $quantity;
            }
        }

        return $resolved;
    }

    /**
     * @return array{per_property:int,per_device:int}
     */
    public function counts(User $user): array
    {
        $propertyIds = Property::query()
            ->visibleTo($user)
            ->where('active', true)
            ->pluck('id');

        return [
            'per_property' => $propertyIds->count(),
            'per_device' => PropertyLock::query()->whereIn('property_id', $propertyIds)
                ->whereNotNull('seam_device_id')->where('seam_device_id', '!=', '')->count(),
        ];
    }
}
