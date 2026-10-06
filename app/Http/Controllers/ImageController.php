<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageController extends Controller
{
    public function show($path)
    {
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }
        return response(Storage::disk('public')->get($path))
            ->header('Content-Type', Storage::disk('public')->mimeType($path));
    }

    /**
     * 1200x630 JPEG copy for link previews (WhatsApp skips images over ~300 KB).
     * Cached on disk; the key includes the original's mtime so a replaced
     * image gets a fresh copy.
     */
    public function og($path)
    {
        $disk = Storage::disk('public');

        if (str_contains($path, '..') || ! $disk->exists($path)) {
            abort(404);
        }

        $dir = storage_path('app/og-cache');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $file = $dir.'/'.sha1($path.'|'.$disk->lastModified($path)).'.jpg';

        if (! is_file($file)) {
            try {
                $manager = new ImageManager(new Driver());
                $manager->read($disk->get($path))->cover(1200, 630)->toJpeg(80)->save($file);
            } catch (\Throwable $e) {
                report($e);
                return redirect('/img/'.$path);   // unreadable by GD: fall back to the original
            }
        }

        return response()->file($file, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
