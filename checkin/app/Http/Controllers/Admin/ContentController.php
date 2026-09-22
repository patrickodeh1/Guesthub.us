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

        return view('admin.content.page-form', compact('property', 'category', 'page', 'source'));
    }

    public function updatePage(Request $request, Property $property, Category $category)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'active' => ['nullable', 'boolean'],
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

        return redirect()->route('admin.guest-guide.show', $property->id)->with('success', 'Category page saved.');
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

    public function editAmenity(Amenity $amenity)
    {
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
        foreach ($amenity->images ?? [] as $image) {
            Storage::disk('public')->delete($image);
        }

        $amenity->delete();
        ActivityLog::record('amenity_deleted', "{$amenity->title} amenity deleted.", 'delete');

        return back()->with('success', 'Amenity deleted.');
    }
}
