<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Setting;
use App\Services\CancellationPolicy;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class FeatureController extends Controller
{
    public function index()
    {
        return view('admin.features.index', [
            'features' => Feature::query()->orderBy('name')->get(),
            'policies' => collect(array_keys(CancellationPolicy::DEFAULTS))->mapWithKeys(fn ($k) => [$k => CancellationPolicy::rules($k)])->all(),
            'refundFee' => Setting::getValue('refund_processing_fee', '0'),
            'platformFee' => Setting::getValue('direct_booking_platform_fee_percent', '0'),
            'processingFee' => Setting::getValue('processing_fee_percent', '0'),
        ]);
    }

    public function update(Request $request, Feature $feature)
    {
        $request->merge([
            'price_cents' => $request->filled('price')
                ? (int) round(((float) $request->input('price')) * 100)
                : null,
        ]);

        $data = $request->validate([
            'is_free' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
            'price_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'billing_unit' => ['nullable', 'in:flat,per_property,per_device'],
        ]);

        unset($data['price']);

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

        return back()->with('success', "{$feature->name} settings saved.");
    }

    public function updateDirectBooking(Request $request)
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $data = $request->validate([
            'refund_processing_fee' => ['required', 'in:0,1'],
            'platform_fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'cancellation' => ['required', 'array'],
            'cancellation.*.full_days' => ['required', 'integer', 'min:0', 'max:365'],
            'cancellation.*.partial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'cancellation.*.partial_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);
        Setting::putValue('refund_processing_fee', $data['refund_processing_fee']);
        Setting::putValue('direct_booking_platform_fee_percent', (string) $data['platform_fee_percent']);
        foreach (array_keys(CancellationPolicy::DEFAULTS) as $name) {
            $row = $data['cancellation'][$name] ?? null;
            if (is_array($row)) {
                Setting::putValue('cancellation_policy_'.$name, json_encode([
                    'full_days' => (int) $row['full_days'],
                    'partial_days' => (int) $row['partial_days'],
                    'partial_percent' => (int) $row['partial_percent'],
                ]));
            }
        }
        ActivityLogService::admin('direct_booking_settings_updated', 'Direct booking settings updated.', 'settings', []);

        return back()->with('success', 'Direct booking settings saved.');
    }
}
