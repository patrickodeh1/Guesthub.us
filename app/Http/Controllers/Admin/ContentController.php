<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Amenity;
use App\Models\Category;
use App\Models\CategoryPage;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Services\MediaService;
use Illuminate\Support\Facades\Storage;

class ContentController extends Controller
{
public function editPage(Property $property, Category $category)
    {
        $page = CategoryPage::firstOrNew([
            'property_id' => $property->id,
            'category_id' => $category->id,
        ], ['title' => $category->title, 'active' => true]);

        // When this property's page inherits from another property, show the
        // source content and offer to break the link rather than editing a
        // copy that would silently be ignored.
        $source = $page->isLinked() ? $page->resolvedPage() : null;

        $otherProperties = Property::visibleTo(auth()->user())
            ->where('id', '!=', $property->id)
            ->orderBy('name')
            ->get();

        return view('admin.content.page-form', compact('property', 'category', 'page', 'source', 'otherProperties'));
    }

    public function updatePage(Request $request, Property $property, Category $category)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'active' => ['nullable', 'boolean'],
            'apply_to_property_ids' => ['nullable', 'array'],
            'apply_to_property_ids.*' => ['integer', 'exists:properties,id'],
            'apply_mode' => ['nullable', 'in:once,sync'],
        ]);

        $page = CategoryPage::firstOrNew(['property_id' => $property->id, 'category_id' => $category->id]);
        $page->fill([
            'title' => $data['title'],
            'content' => $data['content'] ?? null,
            'active' => $request->boolean('active'),
        ]);
        if (array_key_exists('sort_order', $data)) {
            $page->sort_order = $data['sort_order'];
        }
        // Saving this property's own content makes it the master again.
        $page->linked_page_id = null;
        $page->save();

        ActivityLog::record('category_content_updated', "{$category->title} content updated for {$property->name}.", 'content', $page);

        $applied = 0;
        $mode = $data['apply_mode'] ?? 'sync';
        $targetIds = collect($data['apply_to_property_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === $property->id)
            ->unique();

        foreach ($targetIds as $targetId) {
            $target = Property::visibleTo($request->user())->find($targetId);
            if (! $target) {
                continue;
            }

            if (! $target->categories()->whereKey($category->id)->exists()) {
                $sourcePivot = $property->categories()->whereKey($category->id)->first()?->pivot;
                $target->categories()->attach($category->id, [
                    'active' => $sourcePivot?->active ?? true,
                    'custom_title' => $sourcePivot?->custom_title,
                    'custom_description' => $sourcePivot?->custom_description,
                    'header_image' => $sourcePivot?->header_image,
                ]);
            }

            $targetPage = CategoryPage::firstOrNew([
                'property_id' => $target->id,
                'category_id' => $category->id,
            ]);
            $targetPage->title = $targetPage->title ?: $page->title;
            $targetPage->sort_order = (int) ($targetPage->sort_order ?: ($page->sort_order ?? 0));
            $targetPage->active = $page->active;

            if ($mode === 'once') {
                $targetPage->title = $page->title;
                $targetPage->content = $page->content;
                $targetPage->image_1 = $page->image_1;
                $targetPage->image_2 = $page->image_2;
                $targetPage->image_3 = $page->image_3;
                $targetPage->linked_page_id = null;
            } else {
                $targetPage->linked_page_id = $page->id;
            }

            $targetPage->save();
            $applied++;
        }

        $message = 'Category page saved.';
        if ($applied > 0) {
            $label = $mode === 'once' ? 'copied to' : 'synced with';
            $message .= " {$category->title} {$label} {$applied} other ".($applied === 1 ? 'unit' : 'units').'.';
            ActivityLog::record('category_content_applied', "{$category->title} from {$property->name} {$label} {$applied} other unit(s).", 'content', $page);
        }

        return redirect()->route('admin.guest-guide.show', $property->id)->with('success', $message);
    }

    /**
     * Break a shared link and keep an editable local copy of the content, so a
     * single unit can diverge from the shared version (e.g. its own Wi-Fi).
     */
    public function unlinkPage(Property $property, Category $category)
    {
        $page = CategoryPage::where('property_id', $property->id)
            ->where('category_id', $category->id)
            ->firstOrFail();

        if ($page->isLinked()) {
            $source = $page->resolvedPage();
            $page->fill([
                'title' => $source->title,
                'content' => $source->content,
                'image_1' => $source->image_1,
                'image_2' => $source->image_2,
                'image_3' => $source->image_3,
            ]);
            $page->linked_page_id = null;
            $page->save();

            ActivityLog::record('category_content_unlinked', "{$category->title} content unlinked from shared source for {$property->name}.", 'content', $page);
        }

        return redirect()->route('admin.content.edit', [$property, $category])
            ->with('success', 'This property now has its own copy you can customize.');
    }

    public function updateAssignment(Request $request, Property $property, Category $category)
    {
        abort_unless($property->categories()->whereKey($category->id)->exists(), 404);

        $data = $request->validate([
            'custom_title' => ['nullable', 'string', 'max:255'],
            'custom_description' => ['nullable', 'string'],
            'header_image' => ['nullable', 'image', 'max:10240'],
            'active' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('header_image')) {
            $data['header_image'] = $request->file('header_image')->store('category-headers', 'public');
            MediaService::register($data['header_image'], $request->file('header_image')->getClientOriginalName(), $request->file('header_image')->getSize(), 'Category Headers');
        } else {
            unset($data['header_image']);
        }

        $data['active'] = $request->boolean('active');
        $property->categories()->updateExistingPivot($category->id, $data);
        ActivityLog::record('category_settings_updated', "{$category->title} settings updated for {$property->name}.", 'categories', $property);

        return back()->with('success', 'Property category settings saved.');
    }

    public function amenitiesIndex(Property $property)
    {
        return view('admin.content.amenities-index', [
            'property' => $property,
            'amenities' => $property->amenities,
        ]);
    }

    public function createAmenity(Property $property)
    {
        return view('admin.content.amenity-form', [
            'property' => $property,
            'amenity' => new Amenity(),
        ]);
    }

    private function authorizeAmenity(Amenity $amenity): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->hasRole('admin') || \App\Models\Property::visibleTo($u)->whereKey($amenity->property_id)->exists()), 403, 'You do not have access to this property.');
    }

    public function editAmenity(Amenity $amenity)
    {
        $this->authorizeAmenity($amenity);
        return view('admin.content.amenity-form', [
            'property' => $amenity->property,
            'amenity' => $amenity,
        ]);
    }

    public function storeAmenity(Request $request, Property $property)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:80'],
            'details' => ['nullable', 'string'],
            'images.*' => ['nullable', 'image', 'max:10240'],
            'existing_images' => ['nullable', 'array'],
            'existing_images.*' => ['string'],
            'active' => ['nullable', 'boolean'],
        ]);

        $uploaded = collect($request->file('images', []))->map(function ($file) {
            $path = $file->store('amenities', 'public');
            MediaService::register($path, $file->getClientOriginalName(), $file->getSize(), 'Amenity Images');
            return $path;
        });
        $data['images'] = $uploaded->merge($request->input('existing_images', []))->values()->all();
        unset($data['existing_images']);
        $data['active'] = $request->boolean('active');
        $amenity = $property->amenities()->create($data);
        ActivityLog::record('amenity_created', "{$amenity->title} amenity added to {$property->name}.", 'amenities', $amenity);

        return redirect()->route('admin.amenities.index', $property)->with('success', 'Amenity added.');
    }

    public function updateAmenity(Request $request, Amenity $amenity)
    {
        $this->authorizeAmenity($amenity);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:80'],
            'details' => ['nullable', 'string'],
            'images.*' => ['nullable', 'image', 'max:10240'],
            'existing_images' => ['nullable', 'array'],
            'existing_images.*' => ['string'],
            'active' => ['nullable', 'boolean'],
        ]);

        $uploaded = collect($request->file('images', []))->map(function ($file) {
            $path = $file->store('amenities', 'public');
            MediaService::register($path, $file->getClientOriginalName(), $file->getSize(), 'Amenity Images');
            return $path;
        });
        $data['images'] = $uploaded->merge($request->input('existing_images', []))->values()->all();
        unset($data['existing_images']);
        $data['active'] = $request->boolean('active');
        $amenity->update($data);
        ActivityLog::record('amenity_updated', "{$amenity->title} amenity updated for {$amenity->property->name}.", 'amenities', $amenity);

        return redirect()->route('admin.amenities.index', $amenity->property)->with('success', 'Amenity updated.');
    }

    public function deleteAmenity(Amenity $amenity)
    {
        $this->authorizeAmenity($amenity);
        foreach ($amenity->images ?? [] as $image) {
            Storage::disk('public')->delete($image);
        }

        $amenity->delete();
        ActivityLog::record('amenity_deleted', "{$amenity->title} amenity deleted.", 'delete');

        return back()->with('success', 'Amenity deleted.');
    }
}
