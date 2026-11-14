<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\MediaFolder;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function callAction($method, $parameters)
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'owner', 'company', 'manager']), 403, 'You cannot use the media library.');

        return $this->{$method}(...array_values($parameters));
    }

    private function isAdmin(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    // Files: admin sees all, everyone else only the files they uploaded.
    private function files()
    {
        return MediaFile::query()->when(! $this->isAdmin(), fn ($q) => $q->where('created_by', auth()->id()));
    }

    // Folders to browse: their own, plus any folder that holds one of their files (e.g. the shared "Category Icons" folder).
    private function folders()
    {
        return MediaFolder::query()->when(! $this->isAdmin(), fn ($q) => $q->where(function ($w) {
            $w->where('media_folders.created_by', auth()->id())
              ->orWhereIn('media_folders.id', MediaFile::query()->where('created_by', auth()->id())->whereNotNull('media_folder_id')->select('media_folder_id'));
        }));
    }

    // Folders they may rename, move or delete: only the ones they created.
    private function ownedFolders()
    {
        return MediaFolder::query()->when(! $this->isAdmin(), fn ($q) => $q->where('created_by', auth()->id()));
    }

    private function authorizeFile(MediaFile $file): void
    {
        abort_unless($this->isAdmin() || (int) $file->created_by === (int) auth()->id(), 403, 'You do not have access to this file.');
    }

    private function authorizeFolder(MediaFolder $folder): void
    {
        abort_unless($this->isAdmin() || (int) $folder->created_by === (int) auth()->id(), 403, 'You do not have access to this folder.');
    }

    public function index(Request $request)
    {
        $folderId = $request->integer('folder_id') ?: null;
        $folder = $folderId ? $this->folders()->findOrFail($folderId) : null;

        return view('admin.media.index', [
            'currentFolder' => $folder,
            'breadcrumb' => $this->breadcrumb($folder),
            'folders' => $this->folders()->where('parent_id', $folderId)->orderBy('name')->get(),
            'files' => $this->files()->where('media_folder_id', $folderId)->latest()->get(),
        ]);
    }

    public function storeFolder(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'exists:media_folders,id'],
        ]);

        if (! empty($data['parent_id'])) {
            abort_unless($this->folders()->whereKey($data['parent_id'])->exists(), 403, 'You do not have access to that folder.');
        }

        MediaFolder::create($data + ['created_by' => auth()->id()]);

        return back()->with('success', 'Folder created.');
    }

    public function destroyFolder(MediaFolder $folder)
    {
        $this->authorizeFolder($folder);
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

        if (! empty($data['media_folder_id'])) {
            abort_unless($this->folders()->whereKey($data['media_folder_id'])->exists(), 403, 'You do not have access to that folder.');
        }

        $path = $request->file('image')->store('media-library', 'public');

        $file = MediaFile::create([
            'created_by' => auth()->id(),
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
        $this->authorizeFile($file);
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
        $folder = $folderId ? $this->folders()->findOrFail($folderId) : null;

        $folders = $this->folders()->where('parent_id', $folderId)->orderBy('name')->get(['id', 'name']);

        $files = $this->files()->where('media_folder_id', $folderId)
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
        if ($targetFolderId) {
            abort_unless($this->folders()->whereKey($targetFolderId)->exists(), 403, 'You do not have access to that folder.');
        }

        if (!empty($data['file_ids'])) {
            $this->files()->whereIn('id', $data['file_ids'])->update(['media_folder_id' => $targetFolderId]);
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
            $this->ownedFolders()->whereIn('id', $folderIds)->update(['parent_id' => $targetFolderId]);
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
            $files = $this->files()->whereIn('id', $data['file_ids'])->get();
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
            $this->ownedFolders()->whereIn('id', $data['folder_ids'])->get()->each->delete();
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
            || $db::table('amenities')->where('images', 'like', '%' . $path . '%')->exists()
            || $db::table('listing_photos')
                ->where(fn ($query) => $query->where('path', $path)->orWhere('thumbnail_path', $path))
                ->exists();
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
