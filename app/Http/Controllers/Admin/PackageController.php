<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\HostPackage;
use App\Models\Package;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PackageController extends Controller
{
    private function adminOnly(Request $request): void
    {
        abort_unless($request->user()->hasRole('admin'), 403);
    }

    public function index(Request $request)
    {
        $this->adminOnly($request);

        return view('admin.packages.index', [
            'packages' => Package::query()->orderBy('sort_order')->orderBy('id')->get(),
            'features' => Feature::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->adminOnly($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $base = Str::slug($data['name'], '_') ?: 'package';
        $key = $base;
        $n = 2;
        while (Package::query()->where('key', $key)->exists()) {
            $key = $base.'_'.$n++;
        }
        $package = Package::create([
            'key' => $key,
            'name' => $data['name'],
            'sort_order' => (int) Package::query()->max('sort_order') + 1,
        ]);
        ActivityLogService::admin('package_created', "Package {$package->name} created.", 'settings', [
            'subject_type' => Package::class, 'subject_id' => $package->id,
        ]);

        return back()->with('success', "{$package->name} created. Set its price and features, then save.");
    }

    public function updateAll(Request $request)
    {
        $this->adminOnly($request);
        $data = $request->validate([
            'packages' => ['nullable', 'array'],
            'packages.*.name' => ['required', 'string', 'max:80'],
            'packages.*.description' => ['nullable', 'string', 'max:500'],
            'packages.*.price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'packages.*.trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'packages.*.parent_id' => ['nullable', 'integer', 'exists:packages,id'],
            'packages.*.active' => ['required', 'boolean'],
            'packages.*.features' => ['nullable', 'array'],
            'packages.*.features.*' => ['string', 'exists:features,key'],
            'feats' => ['nullable', 'array'],
            'feats.*.is_free' => ['required', 'boolean'],
            'feats.*.active' => ['required', 'boolean'],
            'feats.*.price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'feats.*.billing_unit' => ['required', 'in:flat,per_property,per_device'],
        ]);

        $packages = Package::query()->get()->keyBy('id');
        $input = $data['packages'] ?? [];

        // Check for loops using the parents as they will be after this save.
        $parents = [];
        foreach ($packages as $id => $p) {
            $row = $input[$id] ?? null;
            $parents[$id] = $row ? (($row['parent_id'] ?? null) ?: null) : $p->parent_id;
        }
        foreach ($parents as $id => $_) {
            $node = $parents[$id];
            for ($i = 0; $node && $i < 20; $i++) {
                if ((int) $node === (int) $id) {
                    throw ValidationException::withMessages(['packages' => 'A package cannot build on itself or on one of its own add-ons.']);
                }
                $node = $parents[$node] ?? null;
            }
        }

        DB::transaction(function () use ($packages, $input, $data, $parents) {
            foreach ($packages as $id => $package) {
                $row = $input[$id] ?? null;
                if (! $row) {
                    continue;
                }
                $package->update([
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'price_cents' => isset($row['price']) && $row['price'] !== '' ? (int) round(((float) $row['price']) * 100) : 0,
                    'trial_days' => isset($row['trial_days']) && $row['trial_days'] !== '' ? (int) $row['trial_days'] : null,
                    'parent_id' => $parents[$id],
                    'active' => (bool) $row['active'],
                ]);
                DB::table('package_features')->where('package_id', $package->id)->delete();
                foreach (array_unique($row['features'] ?? []) as $key) {
                    DB::table('package_features')->insert(['package_id' => $package->id, 'feature_key' => $key]);
                }
            }
            foreach (($data['feats'] ?? []) as $key => $row) {
                $feature = Feature::query()->where('key', $key)->first();
                if (! $feature) {
                    continue;
                }
                $feature->update([
                    'is_free' => (bool) $row['is_free'],
                    'active' => (bool) $row['active'],
                    'billing_unit' => $row['billing_unit'],
                    'price_cents' => isset($row['price']) && $row['price'] !== '' ? (int) round(((float) $row['price']) * 100) : null,
                ]);
            }
        });
        ActivityLogService::admin('packages_saved', 'Packages and feature prices updated.', 'settings', []);

        return back()->with('success', 'Packages and feature prices saved.');
    }

    public function destroy(Request $request, Package $package)
    {
        $this->adminOnly($request);
        if (HostPackage::query()->where('package_id', $package->id)->exists()) {
            return back()->withErrors(['package' => 'Hosts are on this package. Move them to another package first, or mark it inactive.']);
        }
        if (Package::query()->where('parent_id', $package->id)->exists()) {
            return back()->withErrors(['package' => 'Another package builds on this one. Change that package first.']);
        }
        $name = $package->name;
        $package->delete();
        ActivityLogService::admin('package_deleted', "Package {$name} deleted.", 'settings', []);

        return back()->with('success', "{$name} deleted.");
    }
}
