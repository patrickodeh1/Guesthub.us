<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;

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
}
