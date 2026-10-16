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
        ]);
    }

    public function store(Request $request, Property $property)
    {
        $request->validate([
            'images'       => ['required', 'array'],
            'images.*'     => ['image', 'max:15360'],
            'room_id'      => ['nullable', 'integer'],
            'instructions' => ['nullable', 'string', 'max:20000'],
        ]);

        $roomId = $request->input('room_id');
        $roomId = ($roomId && $property->rooms()->whereKey($roomId)->exists()) ? (int) $roomId : null;
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

    public function destroy(PropertyPhotoReference $reference)
    {
        Storage::disk('public')->delete($reference->image_path);
        $reference->delete();
        return back()->with('status', 'Photo removed.');
    }

    public function saveRoom(Request $request, Property $property, int $roomId)
    {
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
        $data = $request->validate(['instructions' => ['nullable', 'string', 'max:20000']]);

        $html = strip_tags((string) ($data['instructions'] ?? ''), '<p><br><strong><b><em><i><u><s><strike><del><span><ul><ol><li><h1><h2><h3><h4><h5><h6><div><a><img><table><thead><tbody><tr><td><th><blockquote><hr><sub><sup><pre><code>');
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '', $html);
        $plain = trim(preg_replace('/(&nbsp;|\s)+/u', ' ', strip_tags($html)));

        $reference->forceFill(['instructions' => $plain === '' ? null : $html])->save();

        return back()->with('status', 'Photo instructions saved.');
    }
}
