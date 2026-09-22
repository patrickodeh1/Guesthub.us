<?php

namespace App\Services;

use App\Models\MediaFile;
use App\Models\MediaFolder;
use Illuminate\Support\Facades\Storage;

class MediaService
{
    public static function register(string $path, string $originalName = null, int $size = null, ?string $autoFolder = null): void
    {
        if (!$path) return;

        $exists = MediaFile::where('path', $path)->exists();
        if ($exists) return;

        MediaFile::create([
            'media_folder_id' => $autoFolder ? self::resolveFolder($autoFolder) : null,
            'path'            => $path,
            'original_name'   => $originalName ?? basename($path),
            'size'            => $size ?? Storage::disk('public')->size($path),
        ]);
    }

    private static function resolveFolder(string $name): int
    {
        return MediaFolder::firstOrCreate(
            ['name' => $name, 'parent_id' => null, 'property_id' => null],
        )->id;
    }
}
