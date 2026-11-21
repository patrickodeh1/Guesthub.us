<?php

namespace App\Http\Controllers;

use App\Models\ListingPhoto;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChannelMediaController extends Controller
{
    public function show(ListingPhoto $listingPhoto): StreamedResponse
    {
        abort_unless($listingPhoto->listing?->repull_listing_id, 404);
        abort_unless(Storage::disk('public')->exists($listingPhoto->path), 404);

        return Storage::disk('public')->response($listingPhoto->path, null, [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
