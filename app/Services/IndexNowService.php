<?php

namespace App\Services;

use App\Models\PropertyListing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class IndexNowService
{
    public function notifyListing(PropertyListing $listing): void
    {
        $key = trim((string) config('services.indexnow.key'));
        if ($key === '') {
            return;
        }

        $url = route('public.listing.show', $listing->listing_slug);
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return;
        }

        try {
            $response = Http::timeout(5)->post(config('services.indexnow.endpoint'), [
                'host' => $host,
                'key' => $key,
                'keyLocation' => url('/'.$key.'.txt'),
                'urlList' => [$url],
            ]);
            if (! $response->successful()) {
                Log::warning('IndexNow notification failed', ['status' => $response->status(), 'url' => $url]);
            }
        } catch (Throwable $exception) {
            Log::warning('IndexNow notification failed', ['exception' => $exception::class, 'url' => $url]);
        }
    }
}
