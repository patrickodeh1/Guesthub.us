<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstructionStep;
use App\Models\InstructionStepImage;
use App\Models\Property;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Intervention\Image\ImageManager;

class InstructionStepController extends Controller
{
    /**
     * Resize + compress an uploaded image before storing, so full-resolution
     * phone camera photos (often 8-12MB) don't get served as-is into small
     * thumbnail slots on the steps list. Max width 1600px, JPEG quality 75.
     * Keeps aspect ratio, never upscales a smaller image.
     */
    /**
     * Resize + compress an uploaded image before storing, so full-resolution
     * phone camera photos (often 8-12MB) don't get served as-is into small
     * thumbnail slots. Max width 1600px, JPEG quality 75.
     *
     * Tries Imagick first (most reliable JPEG support across hosts), falls
     * back to GD if Imagick isn't available. If image processing fails for
     * any reason (missing codec, corrupt file, etc.), falls back to storing
     * the original upload untouched rather than blocking the save entirely.
     */
    private function storeResizedImage($uploadedFile, string $directory): string
    {
        $driverClasses = [];
        if (extension_loaded('imagick')) {
            $driverClasses[] = \Intervention\Image\Drivers\Imagick\Driver::class;
        }
        if (extension_loaded('gd')) {
            $driverClasses[] = \Intervention\Image\Drivers\Gd\Driver::class;
        }

        foreach ($driverClasses as $driverClass) {
            try {
                $manager = new ImageManager(new $driverClass());
                $image = $manager->read($uploadedFile->getRealPath());
                $image->scaleDown(width: 1600);

                $filename = $directory . '/' . uniqid() . '.jpg';
                $encoded = $image->toJpeg(75);

                \Illuminate\Support\Facades\Storage::disk('public')->put($filename, (string) $encoded);

                return $filename;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Image resize failed, trying next driver or falling back to original.', [
                    'driver' => $driverClass,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        // No driver could process the image — store the original file as-is
        // so the save never fails outright, even if resizing didn't happen.
        \Illuminate\Support\Facades\Log::warning('No working image driver available, storing original unresized.');
        return $uploadedFile->store($directory, 'public');
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

        return view('admin.instructions.index', [
            'properties' => $properties,
        ]);
    }

    public function forProperty(Property $property)
    {
        $stepsByType = [
            'checkin'  => InstructionStep::where('property_id', $property->id)->where('type', 'checkin')->orderBy('sort_order')->get(),
            'checkout' => InstructionStep::where('property_id', $property->id)->where('type', 'checkout')->orderBy('sort_order')->get(),
        ];

        return view('admin.instructions.show', [
            'property' => $property,
            'stepsByType' => $stepsByType,
        ]);
    }

    public function create(Request $request)
    {
        $propertyParam = $request->get('property_id');
        $type = $request->get('type', 'checkin');
        $selectedProperty = Property::findOrFail($propertyParam);

        return view('admin.instructions.form', [
            'properties' => Property::orderBy('name')->get(),
            'selectedProperty' => $selectedProperty,
            'type' => $type,
            'step' => new InstructionStep(['type' => $type, 'property_id' => $selectedProperty->id]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] = InstructionStep::where('property_id', $data['property_id'])
            ->where('type', $data['type'])
            ->max('sort_order') + 1;

        $step = InstructionStep::create($data);

        $this->storeGalleryImages($request, $step);

        return redirect()
            ->route('admin.instructions.show', $step->property_id)
            ->with('success', 'Step added.');
    }

    public function edit(InstructionStep $instruction)
    {
        return view('admin.instructions.form', [
            'properties' => Property::orderBy('name')->get(),
            'selectedProperty' => $instruction->property,
            'type' => $instruction->type,
            'step' => $instruction->load('images'),
        ]);
    }

    public function update(Request $request, InstructionStep $instruction)
    {
        $data = $this->validated($request, $instruction);

        $instruction->update($data);

        $this->storeGalleryImages($request, $instruction);

        return redirect()
            ->route('admin.instructions.show', $instruction->property_id)
            ->with('success', 'Step updated.');
    }

    public function destroy(InstructionStep $instruction)
    {
        $propertyId = $instruction->property_id;
        $type = $instruction->type;

        $instruction->delete();

        return redirect()
            ->route('admin.instructions.show', $propertyId)
            ->with('success', 'Step deleted.');
    }

    public function reorder(Request $request)
    {
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        foreach ($request->ids as $order => $id) {
            InstructionStep::where('id', $id)->update(['sort_order' => $order]);
        }
        return response()->json(['ok' => true]);
    }

    public function destroyImage(InstructionStepImage $image)
    {
        \Illuminate\Support\Facades\Storage::disk('public')->delete($image->image_path);
        $image->delete();
        return response()->json(['ok' => true]);
    }

    private function storeGalleryImages(Request $request, InstructionStep $step): void
    {
        $libraryPaths = array_filter($request->input('gallery_library_paths', []));

        if (!$request->hasFile('images') && !$libraryPaths) {
            return;
        }

        $nextOrder = $step->images()->max('sort_order') + 1;

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $this->storeResizedImage($file, 'instruction-steps');
                MediaService::register($path, $file->getClientOriginalName(), \Illuminate\Support\Facades\Storage::disk('public')->size($path), 'Step Images');
                InstructionStepImage::create([
                    'instruction_step_id' => $step->id,
                    'image_path' => $path,
                    'sort_order' => $nextOrder++,
                ]);
            }
        }

        foreach ($libraryPaths as $path) {
            InstructionStepImage::create([
                'instruction_step_id' => $step->id,
                'image_path' => $path,
                'sort_order' => $nextOrder++,
            ]);
        }
    }

    private function validated(Request $request, ?InstructionStep $step = null): array
    {
        $data = $request->validate([
            'property_id'          => ['nullable', 'exists:properties,id'],
            'type'                 => ['required', 'in:checkin,checkout,parking'],
            'action'               => ['nullable', 'in:content,door_lock'],
            'title'                => ['required', 'string', 'max:255'],
            'content'              => ['nullable', 'string'],
            'image_path'           => ['nullable', 'image', 'max:10240'],
            'existing_image_path'  => ['nullable', 'string'],
            'active'               => ['nullable', 'boolean'],
            'visibility'           => ['nullable', 'in:all,parkers_only,non_parkers_only'],
            'images'               => ['nullable', 'array'],
            'images.*'             => ['nullable', 'image', 'max:10240'],
            'gallery_library_paths'   => ['nullable', 'array'],
            'gallery_library_paths.*' => ['nullable', 'string', 'exists:media_files,path'],
        ]);

        $data['property_id'] = $data['property_id'] ?: null;

        if ($request->hasFile('image_path')) {
            $originalFile = $request->file('image_path');
            $data['image_path'] = $this->storeResizedImage($originalFile, 'instruction-steps');
            MediaService::register($data['image_path'], $originalFile->getClientOriginalName(), \Illuminate\Support\Facades\Storage::disk('public')->size($data['image_path']), 'Step Images');
        } elseif ($request->filled('existing_image_path')) {
            $data['image_path'] = $request->input('existing_image_path');
        } elseif ($step) {
            unset($data['image_path']);
        }

        unset($data['existing_image_path']);

        $data['active'] = $request->boolean('active', true);
        $data['visibility'] = $request->input('visibility', 'all');
        return $data;
    }
}
