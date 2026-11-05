<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Services\MediaService;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function callAction($method, $parameters)
    {
        $user = auth()->user();
        abort_unless($user && $user->hasAnyRole(['admin', 'owner', 'company', 'manager']), 403, 'You cannot use categories.');

        // The category list is shared by every property, so only admin changes it.
        if (! in_array($method, ['assign', 'preview'], true)) {
            abort_unless($user->hasRole('admin'), 403, 'Only administrators can change the category list.');
        }

        return $this->{$method}(...array_values($parameters));
    }

    // Non-admin roles may assign only shared administrator defaults.
    private function visibleCategories()
    {
        $user = auth()->user();

        return Category::query()->when(! $user->hasRole('admin'), fn ($q) => $q->whereNull('categories.created_by'));
    }

    private function authorizeCategory(Category $category): void
    {
        $user = auth()->user();
        abort_unless(
            $user->hasRole('admin') || ($category->created_by && in_array((int) $category->created_by, \App\Support\OrgScope::userIds($user), true)),
            403,
            'This is a default category. Create your own category to customise it.'
        );
    }

    public function index()
    {
        return view('admin.categories.index', [
            'categories' => $this->visibleCategories()->orderBy('sort_order')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.categories.form', [
            'category' => new Category(),
            'returnTo' => $request->headers->get('referer'),
        ]);
    }

    public function store(Request $request)
    {
        Category::create($this->validated($request));

        $destination = $request->filled('return_to') ? $request->input('return_to') : route('admin.properties.index');

        return redirect()->to($destination)->with('success', 'Category created.');
    }

    public function edit(Category $category, Request $request)
    {
        $this->authorizeCategory($category);
        return view('admin.categories.form', [
            'category' => $category,
            'returnTo' => $request->headers->get('referer'),
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $this->authorizeCategory($category);
        $category->update($this->validated($request, $category));

        $destination = $request->filled('return_to') ? $request->input('return_to') : route('admin.properties.index');

        return redirect()->to($destination)->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        $this->authorizeCategory($category);
        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    public function assign(Request $request)
    {
        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'category_ids' => ['array'],
            'category_ids.*' => ['exists:categories,id'],
        ]);

        $property = Property::findOrFail($data['property_id']);
        abort_unless(
            $request->user()->hasRole('admin') || Property::visibleTo($request->user())->whereKey($property->id)->exists(),
            403,
            'You do not have access to that property.'
        );
        $allowedIds = $this->visibleCategories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all();
        $sync = collect($data['category_ids'] ?? [])
            ->filter(fn ($id) => in_array((int) $id, $allowedIds, true))
            ->mapWithKeys(fn ($id) => [$id => ['active' => true]])->all();
        $property->categories()->sync($sync);

        return redirect()->route('admin.guest-guide.show', $property->id)->with('success', 'Property categories updated.');
    }

    public function preview(\App\Models\Category $category, \App\Models\Property $property)
    {
        $category = $property->categories->firstWhere('id', $category->id);
        abort_if(!$category, 404, 'This category is not assigned to this property.');

        $page = \App\Models\CategoryPage::where('property_id', $property->id)
            ->where('category_id', $category->id)
            ->where('active', true)
            ->first()?->resolvedPage();

        $booking = new \App\Models\Booking([
            'booking_id'      => 'PREVIEW',
            'token'           => 'preview',
            'guest_name'      => 'Preview Guest',
            'email'           => 'preview@example.com',
            'check_in_date'   => now(),
            'check_out_date'  => now()->addDays(3),
            'parking_needed'  => false,
            'status'          => 'checked_in',
            'gps_verified'    => true,
        ]);
        $booking->id = 0;
        $booking->setRelation('property', $property);
        $property->load(['amenities', 'categories']);

        $categories = $property->categories->filter(fn ($c) => $c->active && $c->pivot->active)->values();

        $locks = $category->action === 'door_lock'
            ? $property->locks->map(fn ($lock) => ['lock' => $lock, 'status' => null])
            : collect();

        // TicketmasterService removed -- see note in GuestController::show.
        $localEvents = collect();
        $eventsTotal = 0;
        $eventsHasMore = false;

        $state = 'guide';

        return view('guest.category', compact('booking', 'category', 'page', 'categories', 'locks', 'localEvents', 'eventsTotal', 'eventsHasMore', 'state'));
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'category_ids' => ['required', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        foreach ($data['category_ids'] as $index => $id) {
            Category::whereKey($id)->update(['sort_order' => ($index + 1) * 10]);
        }

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $isAdmin = (bool) auth()->user()?->hasRole('admin');
        if (! $isAdmin) {
            // Guest URLs use the slug and it must stay unique, so it is generated for non-admins.
            $request->merge(['slug' => null]);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug,'.($category?->id ?? 'NULL')],
            'action' => ['nullable', 'in:content,door_lock'],
            'icon' => ['nullable', 'string', 'max:80'],
            'guest_icon' => ['nullable', 'image', 'max:10240'],
            'existing_guest_icon' => ['nullable', 'string'],
            'header_image' => ['nullable', 'image', 'max:10240'],
            'existing_header_image' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'active' => ['nullable', 'boolean'],
            'is_global' => ['nullable', 'boolean'],
        ]);

        $folderLabels = ['guest_icon' => 'Category Icons', 'header_image' => 'Category Headers'];
        foreach (['guest_icon' => 'category-icons', 'header_image' => 'category-headers'] as $field => $directory) {
            if ($request->hasFile($field)) {
                $data[$field] = $request->file($field)->store($directory, 'public');
                MediaService::register($data[$field], $request->file($field)->getClientOriginalName(), $request->file($field)->getSize(), $folderLabels[$field]);
            } elseif ($request->filled('existing_' . $field)) {
                $data[$field] = $request->input('existing_' . $field);
            } elseif ($field === 'header_image' && $request->boolean('clear_header_image')) {
                $data[$field] = null;
            } elseif ($category?->exists) {
                unset($data[$field]);
            }
            unset($data['existing_' . $field]);
        }

        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['title']);
        if (! $isAdmin) {
            if ($category?->exists) {
                $data['slug'] = $category->slug;
            } else {
                $base = $data['slug'] ?: 'category';
                $n = 1;
                while (Category::where('slug', $data['slug'])->exists()) {
                    $data['slug'] = $base . '-' . (++$n);
                }
            }
        }
        $data['active'] = $request->boolean('active');
        $data['is_global'] = $isAdmin ? $request->boolean('is_global') : (bool) ($category?->is_global ?? false);

        return $data;
    }
}
