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
            $files = MediaFile::whereIn('id', $data['file_ids'])->get();
            foreach ($files as $file) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($file->path);
            }
            MediaFile::whereIn('id', $data['file_ids'])->delete();
        }

        if (!empty($data['folder_ids'])) {
            MediaFolder::whereIn('id', $data['folder_ids'])->get()->each->delete();
        }

        return back()->with('success', 'Selected items deleted.');
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
