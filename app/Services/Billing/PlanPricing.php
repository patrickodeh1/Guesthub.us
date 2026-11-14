<?php

namespace App\Services\Billing;

use App\Models\Feature;
use App\Models\Package;

class PlanPricing
{
    /**
     * Price lines for a package plus selected add-ons.
     * A feature's own price is only charged when the package does not include it.
     * Free and inactive features, and features priced at 0, are never charged.
     *
     * @param array<string,int> $addons feature key => billable quantity; 0 preserves a selection without a paid line
     * @return array{lines: array<int,array{type:string,key:?string,name:string,unit_cents:int,quantity:int}>, total_cents:int}
     */
    public static function quote(?Package $package, array $addons = []): array
    {
        $lines = [];
        $included = $package ? $package->allFeatureKeys() : [];

        if ($package && (int) $package->price_cents > 0) {
            $lines[] = ['type' => 'package', 'key' => $package->key, 'name' => $package->name, 'unit_cents' => (int) $package->price_cents, 'quantity' => 1];
        }

        $features = Feature::query()
            ->where('is_free', false)
            ->where('active', true)
            ->whereIn('key', array_keys($addons))
            ->orderBy('name')
            ->get();

        foreach ($features as $f) {
            if (in_array($f->key, $included, true) || (int) $f->price_cents <= 0) {
                continue;
            }
            $qty = ($f->billing_unit ?: 'flat') === 'flat' ? 1 : max(0, (int) $addons[$f->key]);
            if ($qty < 1) {
                continue;
            }
            $lines[] = ['type' => 'addon', 'key' => $f->key, 'name' => $f->name, 'unit_cents' => (int) $f->price_cents, 'quantity' => $qty];
        }

        $total = 0;
        foreach ($lines as $l) {
            $total += $l['unit_cents'] * $l['quantity'];
        }

        return ['lines' => $lines, 'total_cents' => $total];
    }
}
