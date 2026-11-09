<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class FeatureController extends Controller
{
    public function index()
    {
        return view('admin.features.index', [
            'features' => Feature::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Feature $feature)
    {
        $data = $request->validate([
            'is_free' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
            'price_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);

        $before = $feature->only(['is_free', 'price_cents', 'active']);
        $before = $feature->only(['is_free', 'price_cents', 'active']);
        $feature->update($data);
        ActivityLogService::admin(
            'direct_booking_feature_updated',
            "Direct-booking feature settings updated for {$feature->name}.",
            'settings',
            [
                'subject_type' => Feature::class,
                'subject_id' => $feature->id,
                'old_values' => $before,
                'new_values' => $feature->only(['is_free', 'price_cents', 'active']),
            ],
        );
        ActivityLogService::admin(
            'direct_booking_feature_updated',
            "Direct-booking feature settings updated for {$feature->name}.",
            'settings',
            [
                'subject_type' => Feature::class,
                'subject_id' => $feature->id,
                'old_values' => $before,
                'new_values' => $feature->only(['is_free', 'price_cents', 'active']),
            ],
        );

        return back()->with('success', "{$feature->name} settings saved.");
    }
}
