<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\RepullAmenityCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepullAmenityCatalogController extends Controller
{
    private function adminOnly(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
    }

    public function index(Request $request)
    {
        $this->adminOnly($request);

        return view('admin.repull-amenities.index', [
            'amenities' => RepullAmenityCatalog::query()->orderBy('sort_order')->orderBy('label')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->adminOnly($request);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'canonical_id' => ['required', 'string', 'max:100', 'regex:/\\A[a-z0-9_]+\\z/', 'unique:repull_amenity_catalog,canonical_id'],
            'category' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $data['active'] = $request->boolean('active', true);
        RepullAmenityCatalog::create($data);

        return back()->with('success', 'Repull amenity mapping added.');
    }

    public function update(Request $request, RepullAmenityCatalog $amenity)
    {
        $this->adminOnly($request);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'canonical_id' => ['required', 'string', 'max:100', 'regex:/\\A[a-z0-9_]+\\z/', Rule::unique('repull_amenity_catalog', 'canonical_id')->ignore($amenity->id)],
            'category' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'active' => ['required', 'boolean'],
        ]);
        if ($amenity->canonical_id !== $data['canonical_id']
            && Amenity::query()->where('repull_amenity_id', $amenity->canonical_id)->exists()) {
            return back()->withErrors(['canonical_id' => 'This ID is already used by property amenities. Keep it unchanged and deactivate it if it should no longer sync.']);
        }

        $amenity->update($data);

        return back()->with('success', 'Repull amenity mapping saved.');
    }

    public function destroy(Request $request, RepullAmenityCatalog $amenity)
    {
        $this->adminOnly($request);
        if (Amenity::query()->where('repull_amenity_id', $amenity->canonical_id)->exists()) {
            return back()->withErrors(['amenity' => 'This ID is mapped to one or more properties. Deactivate it instead so it stops syncing without losing the existing mapping.']);
        }
        $amenity->delete();

        return back()->with('success', 'Repull amenity mapping removed. Existing property mappings are left untouched.');
    }
}
