<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Property;
use App\Models\Category;
use App\Models\CategoryPage;
use App\Models\InstructionStep;
use Illuminate\Http\Request;
use App\Services\MediaService;
use Illuminate\Support\Str;

class PropertyController extends Controller
{

    public function guideIndex(Request $request)
    {
        $properties = Property::query()
            ->when($request->search, fn ($query, $search) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
            ))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.guest-guide.index', [
            'properties' => $properties,
        ]);
    }

    public function guide(Property $property)
    {
        $property->load(['categories', 'pages.linkedPage.property', 'pages.linkedPages']);
        $assignedIds = $property->categories->pluck('id')->toArray();
        $categories = Category::orderBy('sort_order')->get();
        $allProperties = Property::orderBy('name')->get();

        return view('admin.guest-guide.show', [
            'property' => $property,
            'categories' => $categories,
            'assignedIds' => $assignedIds,
            'allProperties' => $allProperties,
        ]);
    }

    /**
     * Bulk "copy this guide to other properties": assigns every category this
     * property has (with its per-property title/description/header/active) to
     * each selected property and links their category pages back to this
     * property's pages, so the guide is written once and stays in sync. A
     * single unit can still break away per section via "Customize locally".
     */
    public function copyGuide(Request $request, Property $property)
    {
        $data = $request->validate([
            'target_property_ids' => ['required', 'array', 'min:1'],
            'target_property_ids.*' => ['integer', 'exists:properties,id'],
            'separate_category_ids' => ['nullable', 'array'],
            'separate_category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $targetIds = collect($data['target_property_ids'])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === $property->id)
            ->unique()
            ->values();

        $separateCategoryIds = collect($data['separate_category_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->all();

        $property->load(['categories', 'pages']);

        $count = 0;

        foreach ($targetIds as $targetId) {
            $target = Property::find($targetId);
            if (! $target) {
                continue;
            }

            $sync = $property->categories->mapWithKeys(fn (Category $category) => [
                $category->id => [
                    'active' => $category->pivot->active,
                    'custom_title' => $category->pivot->custom_title,
                    'custom_description' => $category->pivot->custom_description,
                    'header_image' => $category->pivot->header_image,
                ],
            ])->all();
            $target->categories()->syncWithoutDetaching($sync);

            foreach ($property->pages as $page) {
                $source = $page->resolvedPage();

                $targetPage = CategoryPage::firstOrNew([
                    'property_id' => $target->id,
                    'category_id' => $page->category_id,
                ]);
                $targetPage->title = $targetPage->title ?: $page->title;
                $targetPage->active = $page->active;
                $targetPage->sort_order = $targetPage->sort_order ?: $page->sort_order;

                if (in_array($page->category_id, $separateCategoryIds, true)) {
                    // Sections flagged "keep separate" (e.g. Wi-Fi) are given
                    // their own copy so each unit can edit it independently,
                    // never following the original.
                    if (! $targetPage->exists || blank($targetPage->content)) {
                        $targetPage->content = $source->content;
                        $targetPage->image_1 = $source->image_1;
                        $targetPage->image_2 = $source->image_2;
                        $targetPage->image_3 = $source->image_3;
                    }
                    $targetPage->linked_page_id = null;
                } else {
                    $targetPage->linked_page_id = $source->id;
                }

                $targetPage->save();
            }

            $count++;
        }

        ActivityLog::record(
            'property_guide_copied',
            "Guide copied from {$property->name} to {$count} propert".($count === 1 ? 'y' : 'ies').'.',
            'content',
            $property
        );

        return back()->with('success', "Guide copied to {$count} propert".($count === 1 ? 'y' : 'ies').'.');
    }
    public function index(Request $request)
    {
        $properties = Property::query()
            ->when($request->search, fn ($query, $search) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
            ))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.properties.index', compact('properties'));
    }

    public function create()
    {
        return view('admin.properties.form', ['property' => new Property()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['timezone'] = $this->detectTimezone($data['latitude'] ?? null, $data['longitude'] ?? null, $data['timezone'] ?? null);
        $property = Property::create($data);

        foreach (\App\Models\Category::all() as $category) {
            $property->categories()->attach($category->id, ['active' => true]);
        }


        ActivityLog::record('property_created', "{$property->name} was added.", 'properties', $property);

        return redirect()->route('admin.properties.index')->with('success', 'Property created.');
    }

    public function edit(Property $property, Request $request)
    {
        return view('admin.properties.form', [
            'property' => $property,
            'returnTo' => $request->headers->get('referer'),
        ]);
    }

    public function update(Request $request, Property $property)
    {
        $data = $this->validated($request, $property);
        $data['timezone'] = $this->detectTimezone($data['latitude'] ?? null, $data['longitude'] ?? null, $data['timezone'] ?? null);
        $property->update($data);
        ActivityLog::record('property_updated', "{$property->name} was updated.", 'edit', $property);

        $destination = $request->filled('return_to') ? $request->input('return_to') : route('admin.properties.index');

        return redirect()->to($destination)->with('success', 'Property updated.');
    }






    public function destroy(Property $property)
    {
        $property->delete();
        ActivityLog::record('property_deleted', "{$property->name} was deleted.", 'delete');

        return back()->with('success', 'Property deleted.');
    }

    public function duplicate(Request $request, Property $property)
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $baseName = preg_replace('/\s*-\s*Unit\s+.+$/i', '', $property->name);
        $created = [];

        for ($i = 0; $i < $data['count']; $i++) {
            $unitLabel = $this->nextUnitLabel($baseName);

            $copy = $property->replicate(['slug']);
            $copy->name = "{$baseName} - Unit {$unitLabel}";
            $copy->unit_number = $unitLabel;
            $copy->slug = $this->uniqueSlug($copy->name);
            $copy->save();

            foreach ($property->amenities as $amenity) {
                $newAmenity = $amenity->replicate();
                $newAmenity->property_id = $copy->id;
                $newAmenity->save();
            }

            foreach ($property->instructionSteps as $step) {
                $newStep = $step->replicate();
                $newStep->property_id = $copy->id;
                $newStep->save();
            }

            foreach ($property->pages as $page) {
                // New units inherit the original's guide content instead of
                // copying it, so editing the source updates every unit. Use
                // "Customize locally" on a page to break away (e.g. Wi-Fi).
                $source = $page->resolvedPage();

                $newPage = new CategoryPage([
                    'property_id' => $copy->id,
                    'category_id' => $page->category_id,
                    'title' => $page->title,
                    'active' => $page->active,
                ]);
                $newPage->linked_page_id = $source->id;
                $newPage->save();
            }

            foreach ($property->categories as $category) {
                $copy->categories()->attach($category->id, $category->pivot->only([
                    'custom_title', 'custom_description', 'header_image', 'active',
                ]));
            }

            $created[] = $copy;
        }

        ActivityLog::record('property_duplicated', "{$property->name} was duplicated into ".count($created)." unit(s).", 'properties', $property);

        return redirect()->route('admin.properties.index')->with('success', count($created).' unit(s) created from '.$property->name.'.');
    }

    private function nextUnitLabel(string $baseName): int
    {
        $count = Property::where('name', 'like', $baseName.' - Unit %')->count();
        return $count + 2; // original counts as "Unit 1" implicitly
    }

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $i = 1;
        while (Property::where('slug', $slug)->exists()) {
            $slug = $original.'-'.(++$i);
        }
        return $slug;
    }

    private function validated(Request $request, ?Property $property = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:properties,slug,'.($property?->id ?? 'NULL')],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'map_embed_url' => ['nullable', 'url'],
            'map_directions_url' => ['nullable', 'url'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'welcome_intro' => ['nullable', 'string'],
            'checkin_instructions' => ['nullable', 'string'],
            'parking_instructions' => ['nullable', 'string'],
            'checkout_instructions' => ['nullable', 'string'],
            'header_image' => ['nullable', 'image', 'max:10240'],
            'existing_header_image' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
            'requires_vehicle_photo' => ['nullable', 'boolean'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'checkout_time' => ['nullable', 'date_format:H:i'],
            'checkin_time' => ['nullable', 'date_format:H:i'],
            'channex_property_id' => ['nullable', 'string', 'max:255', 'unique:properties,channex_property_id,'.($property?->id ?? 'NULL')],
            'deposit_cap_dollars' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'required_incidentals_hold_amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'lockbox_code' => ['nullable', 'string', 'max:255'],
            'parking_rate_sunday' => ['nullable', 'numeric', 'min:0'],
            'parking_rate_monday' => ['nullable', 'numeric', 'min:0'],
            'parking_rate_tuesday' => ['nullable', 'numeric', 'min:0'],
            'parking_rate_wednesday' => ['nullable', 'numeric', 'min:0'],
            'parking_rate_thursday' => ['nullable', 'numeric', 'min:0'],
            'parking_rate_friday' => ['nullable', 'numeric', 'min:0'],
            'parking_rate_saturday' => ['nullable', 'numeric', 'min:0'],
            'early_checkin_rate_8am_12pm' => ['nullable', 'numeric', 'min:0'],
            'early_checkin_rate_12pm_2pm' => ['nullable', 'numeric', 'min:0'],
            'early_checkin_rate_2pm_4pm' => ['nullable', 'numeric', 'min:0'],
            'late_checkout_rate_authorized_per_30min' => ['nullable', 'numeric', 'min:0'],
            'late_checkout_rate_unauthorized_per_30min' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['deposit_cap_cents'] = $request->filled('deposit_cap_dollars')
            ? (int) round((float) $request->input('deposit_cap_dollars') * 100)
            : null;
        unset($data['deposit_cap_dollars']);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['active'] = $request->boolean('active');
        $data['requires_vehicle_photo'] = $request->boolean('requires_vehicle_photo');

        if ($request->hasFile('header_image')) {
            $data['header_image'] = $request->file('header_image')->store('properties', 'public');
            MediaService::register($data['header_image'], $request->file('header_image')->getClientOriginalName(), $request->file('header_image')->getSize(), 'Property Headers');
        } elseif ($request->filled('existing_header_image')) {
            $data['header_image'] = $request->input('existing_header_image');
        } elseif ($property) {
            unset($data['header_image']);
        }
        unset($data['existing_header_image']);

        return $data;
    }

    private function detectTimezone(?float $lat, ?float $lng, ?string $existing): string
    {
        if (!$lat || !$lng) return $existing ?? 'America/New_York';
        try {
            $url = "https://timeapi.io/api/timezone/coordinate?latitude={$lat}&longitude={$lng}";
            $response = file_get_contents($url);
            $data = json_decode($response, true);
            if (isset($data['timeZone'])) return $data['timeZone'];
        } catch (\Throwable $e) {
            // fallback
        }
        return $existing ?? 'America/New_York';
    }




}
