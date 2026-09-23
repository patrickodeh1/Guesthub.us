<?php

namespace App\Http\Controllers;

use App\Models\InstructionalVideo;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Jobs\ProcessInstructionalVideo;

class InstructionalVideoController extends Controller
{
    /**
     * Display a listing of the videos.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasAnyRole(['admin', 'owner', 'company']), 403, 'Unauthorized access.');

        $searchTerm = (string) $request->query('q', '');
        $category = (string) $request->query('category', '');

        $query = InstructionalVideo::query();

        // Admin sees all videos, owner/company sees only videos they created
        if (!$user->hasRole('admin')) {
            $query->where('created_by', $user->id);
        }

        $videos = $query
            ->when($searchTerm !== '', function ($q) use ($searchTerm) {
                $q->where(function ($sub) use ($searchTerm) {
                    $sub->where('title', 'like', "%{$searchTerm}%")
                        ->orWhere('description', 'like', "%{$searchTerm}%");
                });
            })
            ->when($category !== '', function ($q) use ($category) {
                $q->where('category', $category);
            })
            ->with(['properties', 'creator'])
            ->withCount('properties')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.videos.index', compact('videos'));
    }

    /**
     * Show the form for creating a new video.
     */
    public function create(Request $request)
    {
        $user = $request->user();
        Gate::authorize('create', InstructionalVideo::class);

        // Fetch properties the user is allowed to assign to this video
        $propertiesQuery = Property::query()->active();
        if (!$user->hasRole('admin')) {
            $propertiesQuery->where('owner_id', $user->id);
        }
        $properties = $propertiesQuery->orderBy('name')->get();

        $tasks = \App\Models\Task::orderBy('name')->get();

        return view('admin.videos.create', compact('properties', 'tasks'));
    }

    /**
     * Store a newly created video in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        Gate::authorize('create', InstructionalVideo::class);

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', Rule::in(['general', 'room-specific', 'equipment', 'safety', 'process'])],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
            'is_pre_arrival' => ['nullable', 'boolean'],
            'is_required_before_start' => ['nullable', 'boolean'],
            'is_required_during_task' => ['nullable', 'boolean'],
            'required_views' => ['nullable', 'integer', 'min:1'],
            'pre_arrival_display_order' => ['nullable', 'integer'],
            'training_frequency' => ['nullable', 'string', Rule::in(['once_ever', 'once_per_property', 'once_per_assignment', 'every_assignment'])],
            'completion_threshold_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'properties' => ['nullable', 'array'],
            'properties.*' => ['exists:properties,id'],
            'tasks' => ['nullable', 'array'],
            'tasks.*' => ['exists:tasks,id'],
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm,video/x-matroska', 'max:204800'], // max 200MB
            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp'],
        ]);

        // Store video file
        $videoPath = $request->file('video')->store('videos', 'public');

        // Store thumbnail file
        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 'public');
        }

        $video = InstructionalVideo::create([
            'title' => $request->title,
            'description' => $request->description,
            'video_file_path' => $videoPath,
            'thumbnail_path' => $thumbnailPath,
            'duration_seconds' => $request->duration_seconds,
            'category' => $request->category,
            'is_published' => $request->boolean('is_published', false),
            'is_pre_arrival' => $request->boolean('is_pre_arrival', false),
            'is_required_before_start' => $request->boolean('is_required_before_start', false),
            'is_required_during_task' => $request->boolean('is_required_during_task', false),
            'required_views' => $request->integer('required_views') ?: 1,
            'pre_arrival_display_order' => $request->integer('pre_arrival_display_order') ?? 0,
            'training_frequency' => $request->training_frequency ?? 'once_ever',
            'completion_threshold_percent' => $request->integer('completion_threshold_percent') ?: 90,
            'instruction_completion_method' => 'time',
            'training_version' => 1,
            'created_by' => $user->id,
            'processing_status' => 'pending',
        ]);

        if ($request->filled('properties')) {
            // Check that the user has permission to assign these properties
            $allowedPropertyIds = Property::query()->active()
                ->when(!$user->hasRole('admin'), function ($q) use ($user) {
                    $q->where('owner_id', $user->id);
                })
                ->whereIn('id', $request->properties)
                ->pluck('id')
                ->toArray();

            $video->properties()->sync($allowedPropertyIds);
        }
        
        if ($request->filled('tasks')) {
            $video->tasks()->sync($request->tasks);
        }

        try {
            \Log::info('VIDEO_UPLOAD_STARTED', ['user_id' => $user->id, 'file' => $videoPath]);
            // Dispatch FFmpeg background processing job
            // Using dispatchSync to ensure we catch any synchronous errors (since the environment might use sync driver)
            ProcessInstructionalVideo::dispatchSync($video);
            \Log::info('DATABASE_UPDATED', ['video_id' => $video->id]);
        } catch (\Throwable $e) {
            \Log::error('Upload process failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            // Safe cleanup
            if (isset($videoPath) && Storage::disk('public')->exists($videoPath)) {
                Storage::disk('public')->delete($videoPath);
            }
            if (isset($thumbnailPath) && Storage::disk('public')->exists($thumbnailPath)) {
                Storage::disk('public')->delete($thumbnailPath);
            }
            if (isset($video)) {
                $video->delete();
            }
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Video processing failed: ' . $e->getMessage()], 422);
            }
            return back()->withInput()->withErrors(['video' => 'Video processing failed: ' . $e->getMessage()]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'redirect' => route('admin.videos.index')]);
        }
        return redirect()->route('admin.videos.index')
            ->with('success', 'Video uploaded successfully and is now processing in the background. It will be available once processing is complete.');
    }

    /**
     * Show the form for editing the specified video.
     */
    public function edit(InstructionalVideo $video, Request $request)
    {
        $user = $request->user();
        Gate::authorize('update', $video);

        // Fetch properties the user is allowed to assign to this video
        $propertiesQuery = Property::query()->active();
        if (!$user->hasRole('admin')) {
            $propertiesQuery->where('owner_id', $user->id);
        }
        $properties = $propertiesQuery->orderBy('name')->get();
        $attachedPropertyIds = $video->properties->pluck('id')->toArray();

        $tasks = \App\Models\Task::orderBy('name')->get();
        $attachedTaskIds = $video->tasks->pluck('id')->toArray();

        return view('admin.videos.edit', compact('video', 'properties', 'attachedPropertyIds', 'tasks', 'attachedTaskIds'));
    }

    /**
     * Update the specified video in storage.
     */
    public function update(InstructionalVideo $video, Request $request)
    {
        $user = $request->user();
        Gate::authorize('update', $video);

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', Rule::in(['general', 'room-specific', 'equipment', 'safety', 'process'])],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
            'is_pre_arrival' => ['nullable', 'boolean'],
            'is_required_before_start' => ['nullable', 'boolean'],
            'is_required_during_task' => ['nullable', 'boolean'],
            'required_views' => ['nullable', 'integer', 'min:1'],
            'pre_arrival_display_order' => ['nullable', 'integer'],
            'training_frequency' => ['nullable', 'string', Rule::in(['once_ever', 'once_per_property', 'once_per_assignment', 'every_assignment'])],
            'completion_threshold_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'bump_version' => ['nullable', 'boolean'],
            'properties' => ['nullable', 'array'],
            'properties.*' => ['exists:properties,id'],
            'tasks' => ['nullable', 'array'],
            'tasks.*' => ['exists:tasks,id'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm,video/x-matroska', 'max:204800'], // max 200MB
            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp'],
        ]);

        $updateData = [
            'title' => $request->title,
            'description' => $request->description,
            'duration_seconds' => $request->duration_seconds,
            'category' => $request->category,
            'is_published' => $request->boolean('is_published', false),
            'is_pre_arrival' => $request->boolean('is_pre_arrival', false),
            'is_required_before_start' => $request->boolean('is_required_before_start', false),
            'is_required_during_task' => $request->boolean('is_required_during_task', false),
            'required_views' => $request->integer('required_views') ?: 1,
            'pre_arrival_display_order' => $request->integer('pre_arrival_display_order') ?? 0,
            'training_frequency' => $request->training_frequency ?? 'once_ever',
            'completion_threshold_percent' => $request->integer('completion_threshold_percent') ?: 90,
        ];

        if ($request->boolean('bump_version')) {
            $newVersion = ($video->training_version ?? 1) + 1;
            $updateData['training_version'] = $newVersion;
            
            activity()
                ->performedOn($video)
                ->causedBy($user)
                ->withProperties(['old_version' => $video->training_version, 'new_version' => $newVersion])
                ->log("Training Version Bumped");
        }

        // Handle video file replacement
        if ($request->hasFile('video')) {
            if ($video->video_file_path && Storage::disk('public')->exists($video->video_file_path)) {
                // Do not delete the old video until the new one is safely stored.
                // The cleanup will happen when it is overwritten, or we can just delete it now 
                // since the new upload is already validated.
                Storage::disk('public')->delete($video->video_file_path);
            }
            $updateData['video_file_path'] = $request->file('video')->store('videos', 'public');
        }

        // Handle thumbnail file replacement
        if ($request->hasFile('thumbnail')) {
            if ($video->thumbnail_path && Storage::disk('public')->exists($video->thumbnail_path)) {
                Storage::disk('public')->delete($video->thumbnail_path);
            }
            $updateData['thumbnail_path'] = $request->file('thumbnail')->store('thumbnails', 'public');
        }

        $video->update($updateData);

        // The video is already optimized client-side, so no background jobs are needed.

        // Sync properties
        $propertyIds = [];
        if ($request->filled('properties')) {
            $propertyIds = Property::query()->active()
                ->when(!$user->hasRole('admin'), function ($q) use ($user) {
                    $q->where('owner_id', $user->id);
                })
                ->whereIn('id', $request->properties)
                ->pluck('id')
                ->toArray();
        }
        $video->properties()->sync($propertyIds);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'redirect' => route('admin.videos.index')]);
        }
        return redirect()->route('admin.videos.index')
            ->with('success', 'Video updated successfully!');
    }

    /**
     * Remove the specified video from storage.
     */
    public function destroy(InstructionalVideo $video)
    {
        Gate::authorize('delete', $video);

        // Delete files
        if ($video->video_file_path && Storage::disk('public')->exists($video->video_file_path)) {
            Storage::disk('public')->delete($video->video_file_path);
        }

        if ($video->thumbnail_path && Storage::disk('public')->exists($video->thumbnail_path)) {
            Storage::disk('public')->delete($video->thumbnail_path);
        }

        // Pivot records will cascade delete
        $video->delete();

        return redirect()->route('admin.videos.index')
            ->with('success', 'Video deleted successfully!');
    }

    /**
     * Toggle the publish status of a video.
     */
    public function togglePublish(InstructionalVideo $video)
    {
        Gate::authorize('publish', $video);

        $video->update([
            'is_published' => !$video->is_published
        ]);

        $status = $video->is_published ? 'published' : 'unpublished';

        return back()->with('success', "Video successfully {$status}!");
    }
}
