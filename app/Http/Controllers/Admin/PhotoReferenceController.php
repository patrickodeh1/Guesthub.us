<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyPhotoReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class PhotoReferenceController extends Controller
{
    public function index(Property $property)
    {
        return view('admin.properties.photo-guide', [
            'property' => $property,
            'references' => $property->photoReferences,
            'rooms' => $property->rooms()->orderBy('name')->get(),
        ] + $this->guidePanelData($property));
    }

    private function guidePanelData(Property $property): array
    {
        $user = request()->user();
        $linkedSource = $property->photo_guide_source_id ? Property::find($property->photo_guide_source_id) : null;
        $usedByCount = Property::where('photo_guide_source_id', $property->id)->count();
        $sourceOptions = collect();
        if (! $linkedSource && ! $usedByCount) {
            $q = $user->hasRole('admin') ? Property::query() : Property::visibleTo($user);
            $sourceOptions = $q->whereNull('properties.photo_guide_source_id')
                ->where('properties.owner_id', $property->owner_id)
                ->where('properties.id', '!=', $property->id)
                ->whereHas('photoReferences')
                ->orderBy('properties.name')->get();
        }
        $shareTargets = $linkedSource ? collect() : $this->shareTargets($property);
        $unmatched = [];
        if ($linkedSource) {
            $map = $property->guideRoomMap();
            $used = $linkedSource->photoReferences()->whereNotNull('room_id')->where('room_id', '!=', 0)->pluck('room_id')->unique();
            foreach ($used as $rid) {
                if (empty($map[$rid])) {
                    $unmatched[] = optional($linkedSource->rooms->firstWhere('id', $rid))->name ?? 'Unknown room';
                }
            }
        }
        return compact('linkedSource', 'usedByCount', 'sourceOptions', 'unmatched', 'shareTargets');
    }

    /** Same-owner properties this guide can be shared with (already-linked ones included). */
    private function shareTargets(Property $property)
    {
        if ($property->owner_id === null) {
            return collect();
        }
        $user = request()->user();
        $q = $user->hasRole('admin') ? Property::query() : Property::visibleTo($user);

        return $q->where('properties.owner_id', $property->owner_id)
            ->where('properties.id', '!=', $property->id)
            ->where(fn ($w) => $w->whereNull('properties.photo_guide_source_id')
                ->orWhere('properties.photo_guide_source_id', $property->id))
            ->whereNotIn('properties.id', Property::whereNotNull('photo_guide_source_id')->pluck('photo_guide_source_id'))
            ->orderBy('properties.name')->get();
    }

    private function shareGuide(Request $request, Property $property)
    {
        if ($property->photo_guide_source_id) {
            return back()->withErrors(['source_id' => 'This property uses another guide, so it cannot share its own. Unlink it first.']);
        }
        $eligible = $this->shareTargets($property)->pluck('id')->map(fn ($i) => (int) $i);
        $wanted = collect($request->input('target_ids', []))->map(fn ($i) => (int) $i)->unique()->intersect($eligible)->values();

        if ($wanted->isNotEmpty() && ! $property->photoReferences()->exists()) {
            return back()->withErrors(['source_id' => 'Add guide photos to this property before sharing it.']);
        }

        Property::where('photo_guide_source_id', $property->id)
            ->whereIn('id', $eligible)->whereNotIn('id', $wanted)
            ->update(['photo_guide_source_id' => null]);
        if ($wanted->isNotEmpty()) {
            Property::whereIn('id', $wanted)->update(['photo_guide_source_id' => $property->id]);
        }

        return back()->with('status', $wanted->isEmpty() ? 'Sharing turned off for this guide.' : 'This guide is now used by ' . $wanted->count() . ' ' . \Illuminate\Support\Str::plural('property', $wanted->count()) . '.');
    }

    public function link(Request $request, Property $property)
    {
        if ($request->boolean('share_form')) {
            return $this->shareGuide($request, $property);
        }
        $data = $request->validate(['source_id' => ['nullable', 'integer']]);
        $sourceId = $data['source_id'] ?? null;

        if ($sourceId) {
            abort_if((int) $sourceId === (int) $property->id, 422, 'A property cannot use its own guide as a link.');
            $user = $request->user();
            $source = Property::findOrFail($sourceId);
            if (! $source->hasSameOwnerAs($property)) {
                return back()->withErrors(['source_id' => 'Guides can only be shared between properties of the same owner.']);
            }
            abort_unless($user->hasRole('admin') || Property::visibleTo($user)->whereKey($sourceId)->exists(), 403, 'You do not have access to that property.');
            abort_if($source->photo_guide_source_id, 422, 'That property is itself linked to another guide.');
            abort_if(Property::where('photo_guide_source_id', $property->id)->exists(), 422, 'Other properties use this guide, so it cannot link to another one.');
            abort_unless($source->photoReferences()->exists(), 422, 'That property has no guide photos yet.');
        }

        $property->forceFill(['photo_guide_source_id' => $sourceId ?: null])->save();

        return back()->with('status', $sourceId ? 'Now using ' . $source->name . "'s photo guide." : 'Unlinked. This property uses its own photos.');
    }

    public function store(Request $request, Property $property)
    {
        abort_if($property->photo_guide_source_id, 422, 'This property uses a linked guide. Unlink it to add its own photos.');
        $request->validate([
            'images'       => ['required', 'array'],
            'images.*'     => ['image', 'max:15360'],
            'room_id'      => ['required', 'integer'],
            'instructions' => ['nullable', 'string', 'max:20000'],
        ]);

        $roomId = $request->input('room_id');
        abort_unless($property->rooms()->whereKey($roomId)->exists(), 422, 'Select a room.');
        $roomId = (int) $roomId;
        $instructions = strip_tags((string) $request->input('instructions'), '<p><br><strong><b><em><i><u><s><strike><del><span><ul><ol><li><h1><h2><h3><h4><h5><h6><div><a><img><table><thead><tbody><tr><td><th><blockquote><hr><sub><sup><pre><code>');
        $instructions = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $instructions);
        $instructions = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '', $instructions);
        $instructions = trim(preg_replace('/(&nbsp;|\s)+/u', ' ', strip_tags($instructions))) === '' ? null : $instructions;

        $next = (int) $property->photoReferences()->max('sort_order') + 1;
        foreach ($request->file('images') as $file) {
            $path = 'photo-references/' . uniqid() . '.jpg';
            try {
                $img = (new ImageManager(new Driver()))->read($file->getRealPath())->scaleDown(width: 1600);
                Storage::disk('public')->put($path, (string) $img->toJpeg(80));
            } catch (\Throwable $e) {
                report($e);
                $path = $file->store('photo-references', 'public');
            }
            $ref = $property->photoReferences()->create([
                'image_path' => $path,
                'caption'    => null,
                'sort_order' => $next++,
            ]);
            $ref->forceFill(['room_id' => $roomId, 'instructions' => $instructions])->save();
        }

        return back()->with('status', count($request->file('images')) . ' photo(s) added.');
    }

    private function authorizeReference(PropertyPhotoReference $reference): void
    {
        abort_unless(
            auth()->user()->hasRole('admin') || Property::visibleTo(auth()->user())->whereKey($reference->property_id)->exists(),
            403,
            'You do not have access to this photo.'
        );
    }

    public function destroy(PropertyPhotoReference $reference)
    {
        $this->authorizeReference($reference);
        Storage::disk('public')->delete($reference->image_path);
        $reference->delete();
        return back()->with('status', 'Photo removed.');
    }

    public function saveRoom(Request $request, Property $property, int $roomId)
    {
        abort_if($property->photo_guide_source_id, 422, 'This property uses a linked guide.');
        $room = $property->rooms()->whereKey($roomId)->firstOrFail();

        $data = $request->validate([
            'photo_guide_text'    => ['nullable', 'string', 'max:20000'],
            'photo_guide_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
        ]);

        $html = strip_tags((string) ($data['photo_guide_text'] ?? ''), '<p><br><strong><b><em><i><u><s><strike><del><span><ul><ol><li><h1><h2><h3><h4><h5><h6><div><a><img><table><thead><tbody><tr><td><th><blockquote><hr><sub><sup><pre><code>');
        $plain = trim(preg_replace('/(&nbsp;|\s)+/u', ' ', strip_tags($html)));

        $room->forceFill([
            'photo_guide_text'    => $plain === '' ? null : $html,
            'photo_guide_seconds' => $data['photo_guide_seconds'] ?? null,
        ])->save();

        return back()->with('status', $room->name . ' guide saved.');
    }

    public function updateReference(Request $request, PropertyPhotoReference $reference)
    {
        $this->authorizeReference($reference);
        $data = $request->validate(['instructions' => ['nullable', 'string', 'max:20000']]);

        $html = strip_tags((string) ($data['instructions'] ?? ''), '<p><br><strong><b><em><i><u><s><strike><del><span><ul><ol><li><h1><h2><h3><h4><h5><h6><div><a><img><table><thead><tbody><tr><td><th><blockquote><hr><sub><sup><pre><code>');
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '', $html);
        $plain = trim(preg_replace('/(&nbsp;|\s)+/u', ' ', strip_tags($html)));

        $reference->forceFill(['instructions' => $plain === '' ? null : $html])->save();

        return back()->with('status', 'Photo instructions saved.');
    }
}
