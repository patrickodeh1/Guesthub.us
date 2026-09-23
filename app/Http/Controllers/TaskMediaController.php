<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskMediaController extends Controller
{
    public function store(Request $request, Task $task)
    {
        // Validate the array and each file
        $request->validate([
            'media'      => ['required', 'array', 'min:1'],
            'media.*'    => ['file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/x-msvideo,video/webm,video/x-matroska', 'max:204800'],
            'captions'   => ['nullable', 'array'],
            'captions.*' => ['nullable', 'string', 'max:255'],
        ]);

        // Collect files and remove duplicates within THIS request.
        // We use a stable key: name + size + content hash (md5 of tmp path).
        $files = collect($request->file('media', []))
            ->filter() // keep actual files
            ->unique(function (\Illuminate\Http\UploadedFile $f) {
                // hashing tmp file is cheap for typical sizes; robust for duplicates
                return implode('|', [
                    $f->getClientOriginalName(),
                    $f->getSize(),
                    md5_file($f->getRealPath()),
                ]);
            })
            ->values();

        // Determine next sort index
        $start = (int) ($task->media()->max('sort_order') ?? 0);

        foreach ($files as $i => $file) {
            $path = $file->store('task-media', 'public');

            $mime = $file->getMimeType() ?? '';
            $type = str_starts_with($mime, 'video') ? 'video' : 'image';

            $task->media()->create([
                'type'       => $type,
                'url'        => $path,
                'thumbnail'  => $type === 'image' ? $path : null,
                'caption'    => $request->input("captions.$i"),
                'sort_order' => $start + $i + 1,
            ]);
        }

        return back()->with('status', 'Media uploaded');
    }

    public function destroy(Task $task, TaskMedia $media)
    {
        $rawUrl = TaskMedia::normalizePath($media->getRawOriginal('url'));

        // Check how many other TaskMedia records use the same physical file
        $usageCount = 0;
        if ($rawUrl) {
            $usageCount = TaskMedia::where('id', '!=', $media->id)
                                   ->where(function ($q) use ($rawUrl) {
                                       $q->where('url', $rawUrl)
                                         ->orWhere('url', 'LIKE', '%' . $rawUrl);
                                   })
                                   ->count();
        }

        // Only delete the physical file if no other records are using it
        if ($usageCount === 0 && $rawUrl && Storage::disk('public')->exists($rawUrl)) {
            Storage::disk('public')->delete($rawUrl);
            
            // Also clean up thumbnail if it exists and is different from url
            $rawThumbnail = TaskMedia::normalizePath($media->getRawOriginal('thumbnail'));
            if ($rawThumbnail && $rawThumbnail !== $rawUrl && Storage::disk('public')->exists($rawThumbnail)) {
                $thumbUsageCount = TaskMedia::where('id', '!=', $media->id)
                    ->where(function ($q) use ($rawThumbnail) {
                        $q->where('thumbnail', $rawThumbnail)
                          ->orWhere('thumbnail', 'LIKE', '%' . $rawThumbnail);
                    })
                    ->count();
                if ($thumbUsageCount === 0) {
                    Storage::disk('public')->delete($rawThumbnail);
                }
            }
        }

        $media->delete();
        return back()->with('status', 'Media removed');
    }
}
