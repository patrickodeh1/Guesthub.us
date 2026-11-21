<?php

namespace Tests\Feature;

use App\Models\ListingPhoto;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ChannelMediaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_channel_media_url_serves_only_existing_repull_listing_photos(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('direct-listings/cover.webp', 'fake-image-bytes');

        $owner = User::factory()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $listing = PropertyListing::create([
            'property_id' => $property->id,
            'listing_slug' => 'signed-media-'.$property->id,
            'public_title' => 'Signed Media',
            'repull_listing_id' => 'repull-555',
        ]);
        $photo = ListingPhoto::create([
            'listing_id' => $listing->id,
            'path' => 'direct-listings/cover.webp',
            'thumbnail_path' => 'direct-listings/cover.webp',
            'alt' => 'Cover',
            'sort_order' => 0,
        ]);

        $url = URL::temporarySignedRoute('channels.media.show', now()->addMinutes(5), [
            'listingPhoto' => $photo->id,
        ]);

        $this->get($url)->assertOk()
            ->assertHeader('Cache-Control', 'public, max-age=86400')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_unsigned_channel_media_requests_are_rejected(): void
    {
        $this->get(route('channels.media.show', ['listingPhoto' => 123]))
            ->assertForbidden();
    }
}
