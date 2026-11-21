<?php

namespace Tests\Feature;

use App\Models\ChannelConnection;
use App\Models\ListingPhoto;
use App\Models\ListingRoom;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\User;
use App\Services\Channels\RepullListingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingSetupFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_publish_creates_property_adds_rooms_checks_sellable_then_publishes(): void
    {
        config([
            'repull.enabled' => true,
            'repull.api_key' => 'repull-test-key',
            'repull.base_url' => 'https://api.repull.dev',
        ]);
        Http::fake([
            'api.repull.dev/v1/channels/booking/setup' => Http::sequence()
                ->push(['data' => ['propertyId' => 'hotel-100', 'roomId' => 'room-1']])
                ->push(['data' => ['roomId' => 'room-2', 'rateId' => 'rate-2']])
                ->push(['data' => ['sellable' => true, 'blockers' => []]]),
            'api.repull.dev/v1/channels/booking/content*' => Http::response(['data' => ['uploaded' => 1]]),
            'api.repull.dev/v1/listings/repull-100/publish/booking' => Http::response([
                'result' => ['published' => true, 'sections' => ['details', 'photos'], 'errors' => []],
            ]),
        ]);

        $owner = User::factory()->create();
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'address' => '100 Ocean Drive',
            'city' => 'Miami',
            'state' => 'FL',
            'zip' => '33139',
            'country' => 'US',
            'latitude' => 25.78,
            'longitude' => -80.13,
            'beds' => 2,
            'baths' => 1,
            'checkin_time' => '16:00',
            'checkout_time' => '11:00',
        ]);
        $listing = PropertyListing::create([
            'property_id' => $property->id,
            'listing_slug' => 'booking-setup-'.$property->id,
            'public_title' => 'Ocean House',
            'public_description' => str_repeat('Comfortable stay near the beach. ', 32),
            'max_guests' => 4,
            'bedrooms' => 2,
            'base_rate_cents' => 25000,
            'cancellation_policy' => 'moderate',
            'property_type' => 'house',
            'room_type' => 'entire_home',
            'rooms_sync_enabled' => true,
        ]);
        $listing->forceFill(['repull_listing_id' => 'repull-100'])->save();
        ListingPhoto::create([
            'listing_id' => $listing->id,
            'path' => 'test/cover.webp',
            'thumbnail_path' => 'test/cover-thumb.webp',
            'alt' => 'Ocean house',
            'sort_order' => 0,
            'width' => 1200,
            'height' => 800,
        ]);
        ListingRoom::create([
            'listing_id' => $listing->id,
            'room_type' => 'bedroom',
            'room_number' => 1,
            'bed_type' => 'queen_bed',
            'bed_quantity' => 1,
            'beds' => [['bedType' => 'queen_bed', 'quantity' => 1]],
        ]);
        ListingRoom::create([
            'listing_id' => $listing->id,
            'room_type' => 'bedroom',
            'room_number' => 2,
            'bed_type' => 'single_bed',
            'bed_quantity' => 1,
            'beds' => [['bedType' => 'single_bed', 'quantity' => 1]],
        ]);
        ChannelConnection::create([
            'owner_id' => $owner->id,
            'provider' => 'repull',
            'channel' => 'booking',
            'external_account_id' => 'booking-account-100',
            'status' => 'connected',
            'access_type' => 'full_access',
        ]);

        $link = app(RepullListingService::class)->publish($listing->load(['property', 'photos', 'listingRooms']), 'booking', false, 'selected-hotel-9');

        $this->assertSame('hotel-100', $link->external_listing_id);
        $this->assertSame('room-1', $listing->fresh()->listingRooms()->orderBy('room_number')->first()->booking_room_id);
        $this->assertSame('rate-2', $listing->fresh()->listingRooms()->orderBy('room_number')->skip(1)->first()->booking_rate_id);
        $this->assertSame('published', $link->state);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1/listings/repull-100/publish/booking')
            && $request['hotelId'] === 'selected-hotel-9');
        Http::assertSentCount(5);
    }
}
