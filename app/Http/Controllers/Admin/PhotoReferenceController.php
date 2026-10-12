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
            'instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $roomId = $request->input('room_id');
        $roomId = ($roomId && $property->rooms()->whereKey($roomId)->exists()) ? (int) $roomId : null;
        $instructions = trim((string) $request->input('instructions')) ?: null;

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
}
