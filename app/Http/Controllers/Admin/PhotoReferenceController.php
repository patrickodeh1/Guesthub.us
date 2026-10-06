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
        ]);
    }

    public function store(Request $request, Property $property)
    {
        $request->validate([
            'images'   => ['required', 'array'],
            'images.*' => ['image', 'max:15360'],
            'captions' => ['nullable', 'array'],
            'captions.*' => ['nullable', 'string', 'max:255'],
        ]);

        $next = (int) $property->photoReferences()->max('sort_order') + 1;
        foreach ($request->file('images') as $i => $file) {
            $path = 'photo-references/' . uniqid() . '.jpg';
            try {
                $img = (new ImageManager(new Driver()))->read($file->getRealPath())->scaleDown(width: 1600);
                Storage::disk('public')->put($path, (string) $img->toJpeg(80));
            } catch (\Throwable $e) {
                report($e);
                $path = $file->store('photo-references', 'public');
            }
            $property->photoReferences()->create([
                'image_path' => $path,
                'caption'    => $request->input("captions.$i"),
                'sort_order' => $next++,
            ]);
        }

        return back()->with('status', 'Photos added.');
    }

    public function destroy(PropertyPhotoReference $reference)
    {
        Storage::disk('public')->delete($reference->image_path);
        $reference->delete();
        return back()->with('status', 'Photo removed.');
    }
}
