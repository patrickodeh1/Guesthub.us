<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\MediaFolder;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function index(Request $request)
    {
        $folderId = $request->integer('folder_id') ?: null;
        $folder = $folderId ? MediaFolder::findOrFail($folderId) : null;

        return view('admin.media.index', [
            'currentFolder' => $folder,
            'breadcrumb' => $this->breadcrumb($folder),
            'folders' => MediaFolder::where('parent_id', $folderId)->orderBy('name')->get(),
            'files' => MediaFile::where('media_folder_id', $folderId)->latest()->get(),
        ]);
    }

    public function storeFolder(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'exists:media_folders,id'],
        ]);

        MediaFolder::create($data);

        return back()->with('success', 'Folder created.');
    }

    public function destroyFolder(MediaFolder $folder)
    {
        $parentId = $folder->parent_id;
        $folder->delete();

        return redirect()->route('admin.media.index', ['folder_id' => $parentId])
            ->with('success', 'Folder deleted.');
    }

    public function storeFile(Request $request)
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
            'media_folder_id' => ['nullable', 'exists:media_folders,id'],
        ]);

        $path = $request->file('image')->store('media-library', 'public');

        $file = MediaFile::create([
            'media_folder_id' => $data['media_folder_id'] ?? null,
            'path' => $path,
            'original_name' => $request->file('image')->getClientOriginalName(),
            'size' => $request->file('image')->getSize(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'file' => [
                    'id' => $file->id,
                    'url' => $file->url(),
                    'name' => $file->original_name,
                    'path' => $file->path,
                ],
            ]);
        }

        return back()->with('success', 'Image uploaded.');
    }

    public function destroyFile(MediaFile $file)
    {
if ($this->isInUse($file->path)) {
            return back()->with('error', 'This image is in use in a category, amenity, instruction step, property or setting, so it was not deleted.');
        }
        \Illuminate\Support\Facades\Storage::disk('public')->delete($file->path);
        $file->delete();

        return back()->with('success', 'Image deleted.');
    }

    public function picker(Request $request)
    {
        $folderId = $request->integer('folder_id') ?: null;
        $folder = $folderId ? MediaFolder::findOrFail($folderId) : null;

        $folders = MediaFolder::where('parent_id', $folderId)->orderBy('name')->get(['id', 'name']);

        $files = MediaFile::where('media_folder_id', $folderId)
            ->latest()
            ->get()
            ->map(fn($f) => ['id' => $f->id, 'url' => $f->url(), 'name' => $f->original_name, 'path' => $f->path]);

        return response()->json([
            'breadcrumb' => $this->breadcrumb($folder),
            'folders' => $folders,
            'files' => $files,
            'current_folder_id' => $folderId,
        ]);
    }

    public function bulkMove(Request $request)
    {
        $data = $request->validate([
            'file_ids' => ['array'],
            'file_ids.*' => ['integer', 'exists:media_files,id'],
            'folder_ids' => ['array'],
            'folder_ids.*' => ['integer', 'exists:media_folders,id'],
            'target_folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ]);

        $targetFolderId = $data['target_folder_id'] ?? null;

        if (!empty($data['file_ids'])) {
            MediaFile::whereIn('id', $data['file_ids'])->update(['media_folder_id' => $targetFolderId]);
        }

        if (!empty($data['folder_ids'])) {
            // Prevent moving a folder into itself
            $folderIds = array_filter($data['folder_ids'], fn ($id) => $id != $targetFolderId);
            if ($targetFolderId) {
                $blocked = [];
                $cursor = MediaFolder::find($targetFolderId);
                while ($cursor) {
                    $blocked[] = $cursor->id;
                    $cursor = $cursor->parent_id ? MediaFolder::find($cursor->parent_id) : null;
                }
                $folderIds = array_filter($folderIds, fn ($id) => ! in_array((int) $id, $blocked, true));
            }
            MediaFolder::whereIn('id', $folderIds)->update(['parent_id' => $targetFolderId]);
        }

        return back()->with('success', 'Selected items moved.');
    }

    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'file_ids' => ['array'],
            'file_ids.*' => ['integer', 'exists:media_files,id'],
            'folder_ids' => ['array'],
            'folder_ids.*' => ['integer', 'exists:media_folders,id'],
        ]);

        if (!empty($data['file_ids'])) {
            $kept = 0;
            $files = MediaFile::whereIn('id', $data['file_ids'])->get();
            foreach ($files as $file) {
                if ($this->isInUse($file->path)) {
                    $kept++;
                    continue;
                }
                \Illuminate\Support\Facades\Storage::disk('public')->delete($file->path);
                $file->delete();
            }
        }

        if (!empty($data['folder_ids'])) {
            MediaFolder::whereIn('id', $data['folder_ids'])->get()->each->delete();
        }

        return back()->with('success', 'Selected items deleted.' . (! empty($kept) ? " {$kept} image(s) in use were kept." : ''));
    }

    private function isInUse(string $path): bool
    {
        $db = \Illuminate\Support\Facades\DB::class;
        $checks = [
            ['categories', ['icon', 'guest_icon', 'header_image']],
            ['category_pages', ['image_1', 'image_2', 'image_3']],
            ['instruction_steps', ['image_path']],
            ['instruction_step_images', ['image_path']],
            ['properties', ['header_image', 'photo_path']],
            ['settings', ['value']],
        ];
        foreach ($checks as [$table, $cols]) {
            foreach ($cols as $col) {
                if ($db::table($table)->where($col, $path)->exists()) {
                    return true;
                }
            }
        }

        return $db::table('amenities')->where('images', 'like', '%' . str_replace('/', '\\/', $path) . '%')->exists()
            || $db::table('amenities')->where('images', 'like', '%' . $path . '%')->exists();
    }

    private function breadcrumb(?MediaFolder $folder): array
    {
        $trail = [];
        while ($folder) {
            array_unshift($trail, ['id' => $folder->id, 'name' => $folder->name]);
            $folder = $folder->parent;
        }
        return $trail;
    }
}
