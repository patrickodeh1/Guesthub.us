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
            ->when(! $request->user()->hasRole('admin'), fn ($query) => $query->whereIn('properties.id', Property::visibleTo($request->user())->select('properties.id')))
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
            'copyTargets' => $this->copyTargets($property),
        ] + $this->linkInfo($stepsByType));
    }

    /** Same-owner properties the current user may copy steps to. */
    private function copyTargets(Property $property)
    {
        if ($property->owner_id === null) {
            return collect();
        }
        $user = auth()->user();

        return Property::query()
            ->when(! $user->hasRole('admin'), fn ($q) => $q->whereIn('properties.id', Property::visibleTo($user)->select('properties.id')))
            ->where('properties.owner_id', $property->owner_id)
            ->where('properties.id', '!=', $property->id)
            ->orderBy('properties.name')
            ->get(['properties.id', 'properties.name']);
    }

    private function linkInfo(array $stepsByType): array
    {
        $all = collect($stepsByType)->flatMap(fn ($c) => $c->all())->values();
        $srcIds = $all->pluck('linked_from_step_id')->filter()->unique();
        $linkSources = InstructionStep::with('property:id,name')->whereIn('id', $srcIds)->get()->keyBy('id');
        $copyCounts = InstructionStep::whereIn('linked_from_step_id', $all->pluck('id'))
            ->selectRaw('linked_from_step_id, count(*) as c')->groupBy('linked_from_step_id')
            ->pluck('c', 'linked_from_step_id');

        return compact('linkSources', 'copyCounts');
    }

    /** Push the original's content to every synced copy. Order is left alone. */
    private function syncCopies(InstructionStep $source): void
    {
        $rootId = $source->linked_from_step_id ?: $source->id;
        $copies = InstructionStep::where(fn ($q) => $q->where('id', $rootId)->orWhere('linked_from_step_id', $rootId))
            ->where('id', '!=', $source->id)->get();
        if ($copies->isEmpty()) {
            return;
        }
        $source->load('images');
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        foreach ($copies as $copy) {
            $oldImage = $copy->image_path;
            $copy->forceFill([
                'type' => $source->type,
                'action' => $source->action,
                'title' => $source->title,
                'content' => $source->content,
                'image_path' => $this->copyFile($source->image_path),
                'active' => $source->active,
                'visibility' => $source->visibility,
                'show_before_gps' => $source->show_before_gps,
                'for_guest_parking' => $source->for_guest_parking,
                'for_guest_no_parking' => $source->for_guest_no_parking,
                'for_cleaner' => $source->for_cleaner,
                'requires_photo' => $source->requires_photo,
            ])->save();
            if ($oldImage && $oldImage !== $source->image_path && $disk->exists($oldImage)) {
                $disk->delete($oldImage);
            }
            foreach ($copy->images as $img) {
                $disk->delete($img->image_path);
                $img->delete();
            }
            foreach ($source->images as $img) {
                InstructionStepImage::create([
                    'instruction_step_id' => $copy->id,
                    'image_path' => $this->copyFile($img->image_path),
                    'sort_order' => $img->sort_order,
                ]);
            }
        }
    }

    /** Remove a step from its sync group; if it was the root, the next member becomes the new root. */
    private function leaveGroup(InstructionStep $step): void
    {
        if ($step->linked_from_step_id) {
            $step->forceFill(['linked_from_step_id' => null])->save();
            return;
        }
        $members = InstructionStep::where('linked_from_step_id', $step->id)->orderBy('id')->get();
        if ($members->isEmpty()) {
            return;
        }
        $newRoot = $members->first();
        $newRoot->forceFill(['linked_from_step_id' => null])->save();
        InstructionStep::whereIn('id', $members->skip(1)->pluck('id'))->update(['linked_from_step_id' => $newRoot->id]);
    }

    public function unlink(InstructionStep $instruction)
    {
        abort_unless(auth()->user()->hasRole('admin') || \App\Models\Property::visibleTo(auth()->user())->whereKey($instruction->property_id)->exists(), 403, 'You do not have access to this property.');
        $this->leaveGroup($instruction);

        return back()->with('success', '"' . $instruction->title . '" is now its own step and can be edited.');
    }

    private function copyFile(?string $path): ?string
    {
        if (! $path) {
            return $path;
        }
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        if (! $disk->exists($path)) {
            return $path;
        }
        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $dir = trim(dirname($path), './') ?: 'instruction-steps';
        $new = $dir . '/' . uniqid() . '.' . $ext;
        $disk->copy($path, $new);

        return $new;
    }

    public function copy(Request $request, InstructionStep $instruction)
    {
        abort_unless(auth()->user()->hasRole('admin') || \App\Models\Property::visibleTo(auth()->user())->whereKey($instruction->property_id)->exists(), 403, 'You do not have access to this property.');
        $source = $instruction->property;
        if (! $source) {
            return back()->withErrors(['copy' => 'This step applies to every property, so it cannot be copied.']);
        }
        $data = $request->validate(['target_ids' => ['required', 'array', 'min:1'], 'target_ids.*' => ['integer'], 'mode' => ['nullable', 'in:once,sync']], [
            'target_ids.required' => 'Tick at least one property to copy to.',
        ]);
        $allowed = $this->copyTargets($source)->pluck('id')->map(fn ($i) => (int) $i);
        $targets = collect($data['target_ids'])->map(fn ($i) => (int) $i)->unique()->intersect($allowed)->values();
        if ($targets->isEmpty()) {
            return back()->withErrors(['copy' => 'Steps can only be copied to properties with the same owner.']);
        }

        $sync = $request->input('mode') === 'sync';
        $rootId = $instruction->linked_from_step_id ?: $instruction->id;
        if ($sync) {
            $already = InstructionStep::where(fn ($q) => $q->where('id', $rootId)->orWhere('linked_from_step_id', $rootId))->pluck('property_id')->map(fn ($i) => (int) $i)->all();
            $targets = $targets->reject(fn ($id) => in_array($id, $already, true))->values();
            if ($targets->isEmpty()) {
                return back()->withErrors(['copy' => 'Those properties already have a synced copy of this step.']);
            }
        }

        $instruction->load('images');
        $position = (int) array_search($instruction->id, InstructionStep::where('property_id', $instruction->property_id)
            ->where('type', $instruction->type)->orderBy('sort_order')->orderBy('id')->pluck('id')->all(), true);
        foreach ($targets as $targetId) {
            $new = new InstructionStep();
            $new->forceFill([
                'property_id' => $targetId,
                'source_step_id' => null,
                'linked_from_step_id' => $sync ? $rootId : null,
                'type' => $instruction->type,
                'action' => $instruction->action,
                'sort_order' => (int) InstructionStep::where('property_id', $targetId)->where('type', $instruction->type)->max('sort_order') + 1,
                'title' => $instruction->title,
                'content' => $instruction->content,
                'image_path' => $this->copyFile($instruction->image_path),
                'active' => $instruction->active,
                'visibility' => $instruction->visibility,
                'show_before_gps' => $instruction->show_before_gps,
                'for_guest_parking' => $instruction->for_guest_parking,
                'for_guest_no_parking' => $instruction->for_guest_no_parking,
                'for_cleaner' => $instruction->for_cleaner,
                'requires_photo' => $instruction->requires_photo,
            ])->save();

            // Place the copy at the same position as the original and renumber that property's list.
            $ids = InstructionStep::where('property_id', $targetId)->where('type', $instruction->type)
                ->where('id', '!=', $new->id)->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
            array_splice($ids, min($position, count($ids)), 0, [$new->id]);
            foreach ($ids as $i => $id) {
                InstructionStep::where('id', $id)->update(['sort_order' => $i]);
            }

            foreach ($instruction->images as $img) {
                InstructionStepImage::create([
                    'instruction_step_id' => $new->id,
                    'image_path' => $this->copyFile($img->image_path),
                    'sort_order' => $img->sort_order,
                ]);
            }
        }

        return back()->with('success', '"' . $instruction->title . '" copied to ' . $targets->count() . ' ' . \Illuminate\Support\Str::plural('property', $targets->count()) . '.');
    }

    public function create(Request $request)
    {
        $propertyParam = $request->get('property_id');
        $type = $request->get('type', 'checkin');
        $selectedProperty = Property::visibleTo($request->user())->findOrFail($propertyParam);

        return view('admin.instructions.form', [
            'properties' => Property::visibleTo(auth()->user())->orderBy('name')->get(),
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
        abort_unless(auth()->user()->hasRole('admin') || \App\Models\Property::visibleTo(auth()->user())->whereKey($instruction->property_id)->exists(), 403, 'You do not have access to this property.');
        return view('admin.instructions.form', [
            'properties' => Property::visibleTo(auth()->user())->orderBy('name')->get(),
            'selectedProperty' => $instruction->property,
            'type' => $instruction->type,
            'step' => $instruction->load('images'),
        ]);
    }

    public function update(Request $request, InstructionStep $instruction)
    {
        abort_unless(auth()->user()->hasRole('admin') || \App\Models\Property::visibleTo(auth()->user())->whereKey($instruction->property_id)->exists(), 403, 'You do not have access to this property.');
        $data = $this->validated($request, $instruction);

        $instruction->update($data);

        $this->storeGalleryImages($request, $instruction);
        $this->syncCopies($instruction->fresh());

        return redirect()
            ->route('admin.instructions.show', $instruction->property_id)
            ->with('success', 'Step updated.');
    }

    public function destroy(InstructionStep $instruction)
    {
        abort_unless(auth()->user()->hasRole('admin') || \App\Models\Property::visibleTo(auth()->user())->whereKey($instruction->property_id)->exists(), 403, 'You do not have access to this property.');
        $propertyId = $instruction->property_id;
        $type = $instruction->type;

        $this->leaveGroup($instruction);
        $instruction->delete();

        return redirect()
            ->route('admin.instructions.show', $propertyId)
            ->with('success', 'Step deleted.');
    }

    public function reorder(Request $request)
    {
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        foreach ($request->ids as $order => $id) {
            InstructionStep::where('id', $id)->when(! auth()->user()->hasRole('admin'), fn ($q) => $q->whereIn('property_id', \App\Models\Property::visibleTo(auth()->user())->select('id')))->update(['sort_order' => $order]);
        }
        return response()->json(['ok' => true]);
    }

    public function destroyImage(InstructionStepImage $image)
    {
        $stepProperty = $image->instructionStep?->property_id;
        abort_unless(auth()->user()->hasRole('admin') || ($stepProperty && \App\Models\Property::visibleTo(auth()->user())->whereKey($stepProperty)->exists()), 403, 'You do not have access to this property.');
        \Illuminate\Support\Facades\Storage::disk('public')->delete($image->image_path);
        $stepModel = $image->instructionStep;
        $image->delete();
        if ($stepModel) {
            $this->syncCopies($stepModel);
        }
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
            'visibility'           => ['nullable', 'in:all,parkers_only,non_parkers_only,cleaners_only,guests_only'],
            'show_before_gps'      => ['nullable', 'boolean'],
            'images'               => ['nullable', 'array'],
            'images.*'             => ['nullable', 'image', 'max:10240'],
            'gallery_library_paths'   => ['nullable', 'array'],
            'gallery_library_paths.*' => ['nullable', 'string', 'exists:media_files,path'],
        ]);

        $data['property_id'] = $data['property_id'] ?: null;
        if (! $data['property_id'] && ! auth()->user()->hasRole('admin')) {
            abort(403, 'Only administrators can create steps that apply to every property.');
        }
        if ($data['property_id'] && ! auth()->user()->hasRole('admin')
            && ! \App\Models\Property::visibleTo(auth()->user())->whereKey($data['property_id'])->exists()) {
            abort(403, 'You do not have access to this property.');
        }

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
        $data['requires_photo'] = $request->boolean('requires_photo') && $request->boolean('for_cleaner');
        // Audience checkboxes: the admin ticks everyone who should see this step.
        $gp = $request->boolean('for_guest_parking');
        $gn = $request->boolean('for_guest_no_parking');
        $cl = $request->boolean('for_cleaner');
        $data['for_guest_parking'] = $gp;
        $data['for_guest_no_parking'] = $gn;
        $data['for_cleaner'] = $cl;
        // Keep the legacy column roughly in sync (rollback safety only; nothing reads it any more).
        $data['visibility'] = match (true) {
            $gp && $gn && $cl   => 'all',
            $gp && $gn          => 'guests_only',
            $cl && ! $gp && ! $gn => 'cleaners_only',
            $gp && ! $gn        => 'parkers_only',
            $gn && ! $gp        => 'non_parkers_only',
            default             => 'all',
        };
        if (\Illuminate\Support\Facades\Schema::hasColumn('instruction_steps', 'show_before_gps')) {
            $data['show_before_gps'] = $request->boolean('show_before_gps');
        } else {
            unset($data['show_before_gps']);
        }
        return $data;
    }
}
