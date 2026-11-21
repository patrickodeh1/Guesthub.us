<?php

namespace Tests\Feature;

use App\Models\ChannelConnection;
use App\Models\Amenity;
use App\Jobs\RefreshChannelStatus;
use App\Models\ListingRoom;
use App\Models\ListingChannelLink;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\User;
use App\Services\Channels\ListingContentMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ListingChannelAccessTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        Role::findOrCreate('owner');
        $user = User::factory()->create();
        $user->assignRole('owner');

        return $user;
    }

    private function listingFor(User $owner, array $propertyOverrides = []): PropertyListing
    {
        $property = Property::factory()->create(array_merge(['owner_id' => $owner->id], $propertyOverrides));

        return PropertyListing::create([
            'property_id' => $property->id,
            'listing_slug' => 'loft-'.$property->id,
            'public_title' => 'Sunny Loft',
            'public_description' => 'Lovely loft. Book at https://example.com or call 305-555-0142.',
            'max_guests' => 4,
            'bedrooms' => 2,
            'base_rate_cents' => 24000,
            'property_type' => 'house',
            'room_type' => 'private_room',
        ]);
    }

    public function test_another_host_cannot_open_or_publish_a_listing_they_do_not_own(): void
    {
        $a = $this->owner();
        $b = $this->owner();
        $listing = $this->listingFor($a);

        $show = $this->actingAs($b)->get(route('admin.properties.channels.show', $listing->property));
        $this->assertContains($show->status(), [403, 404]);

        $publish = $this->actingAs($b)->post(route('admin.properties.channels.publish', [$listing->property, 'airbnb']));
        $this->assertContains($publish->status(), [403, 404]);
    }

    public function test_publish_is_blocked_until_the_listing_is_complete_and_never_uses_another_hosts_account(): void
    {
        $a = $this->owner();
        $b = $this->owner();
        $listing = $this->listingFor($a);
        // Host B has a connected Airbnb account; host A has none. A must NOT borrow B's.
        ChannelConnection::create(['owner_id' => $b->id, 'provider' => 'repull', 'channel' => 'airbnb', 'external_account_id' => '999', 'status' => 'connected']);

        $service = app(\App\Services\Channels\RepullListingService::class);
        $listing->load('property');

        $this->assertNull($service->connectionFor($listing, 'airbnb'));
        $this->assertArrayHasKey('street', app(ListingContentMapper::class)->missing($listing->load('photos'), 'airbnb'));
    }

    public function test_mapper_reports_missing_fields_strips_contact_details_and_extracts_street(): void
    {
        $owner = $this->owner();
        $listing = $this->listingFor($owner, [
            'address' => '1200 Ocean Drive, Miami Beach, FL 33139', 'city' => 'Miami Beach', 'state' => 'FL', 'zip' => '33139',
            'latitude' => 25.78, 'longitude' => -80.13, 'beds' => 3, 'baths' => 1.5, 'country' => 'US',
        ]);
        $listing->load(['property', 'photos']);
        $mapper = app(ListingContentMapper::class);

        $create = $mapper->createPayload($listing);
        $this->assertSame('1200 Ocean Drive', $create['street']);
        $this->assertSame(240.0, $create['defaultDailyPrice']);
        $this->assertSame('house', $create['propertyType']);
        $this->assertSame('private_room', $mapper->contentPayload($listing)['details']['roomTypeCategory']);
        $this->assertStringNotContainsString('example.com', $create['description']);
        $this->assertStringNotContainsString('555', $create['description']);

        $this->assertSame(['photos' => 'At least one photo'], $mapper->missing($listing, 'airbnb'));
        $this->assertNotEmpty($mapper->warnings($listing));
    }

    public function test_failed_unlist_is_recorded_and_not_reported_as_success(): void
    {
        config(['repull.enabled' => true, 'repull.api_key' => 'sk_live_test', 'repull.base_url' => 'https://api.repull.dev']);
        Http::fake([
            'api.repull.dev/v1/channels/booking/properties/*' => Http::response([
                'error' => ['code' => 'not_ready', 'message' => 'Property cannot be closed yet.'],
            ], 422),
        ]);

        $owner = $this->owner();
        $listing = $this->listingFor($owner, [
            'address' => '1200 Ocean Drive, Miami Beach, FL 33139',
            'city' => 'Miami Beach', 'state' => 'FL', 'zip' => '33139',
            'latitude' => 25.78, 'longitude' => -80.13, 'beds' => 3, 'baths' => 1.5, 'country' => 'US',
        ]);
        $listing->forceFill(['repull_listing_id' => 'repull-listing-1'])->save();
        $connection = ChannelConnection::create([
            'owner_id' => $owner->id, 'provider' => 'repull', 'channel' => 'booking',
            'external_account_id' => 'booking-account-1', 'status' => 'connected', 'access_type' => 'full_access',
        ]);
        $link = ListingChannelLink::create([
            'listing_id' => $listing->id, 'channel' => 'booking', 'connection_id' => $connection->id,
            'external_listing_id' => 'hotel-1', 'state' => 'live',
        ]);

        try {
            app(\App\Services\Channels\RepullListingService::class)->unlist($listing->load('property'), 'booking');
            $this->fail('Expected Repull rejection to be propagated.');
        } catch (\App\Services\Repull\RepullException $e) {
            $this->assertSame('not_ready', $e->errorCode);
        }

        $this->assertSame('error', $link->fresh()->state);
        $this->assertStringContainsString('[not_ready]', $link->fresh()->last_error);
    }


    public function test_mapper_sends_only_explicitly_mapped_active_amenities(): void
    {
        $owner = $this->owner();
        $listing = $this->listingFor($owner, [
            'address' => '1200 Ocean Drive, Miami Beach, FL 33139',
            'city' => 'Miami Beach', 'state' => 'FL', 'zip' => '33139',
            'latitude' => 25.78, 'longitude' => -80.13, 'beds' => 3, 'baths' => 1.5, 'country' => 'US',
        ]);
        $property = $listing->property;
        Amenity::create(['property_id' => $property->id, 'title' => 'Wi-Fi', 'active' => true, 'repull_amenity_id' => 'wifi']);
        Amenity::create(['property_id' => $property->id, 'title' => 'Swimming pool', 'active' => true]);
        Amenity::create(['property_id' => $property->id, 'title' => 'Old amenity', 'active' => false, 'repull_amenity_id' => 'old_value']);

        $payload = app(ListingContentMapper::class)->contentPayload($listing->load(['property', 'photos']));
        $this->assertSame(['wifi'], $payload['amenities']);
    }


    public function test_mapper_sends_complete_configured_rooms_and_beds_only_when_enabled(): void
    {
        $owner = $this->owner();
        $listing = $this->listingFor($owner);
        $listing->forceFill(['rooms_sync_enabled' => true])->save();
        ListingRoom::create([
            'listing_id' => $listing->id,
            'room_type' => 'bedroom',
            'room_number' => 1,
            'room_name' => 'Primary bedroom',
            'bed_type' => 'queen_bed',
            'bed_quantity' => 1,
            'is_private' => true,
        ]);

        $payload = app(ListingContentMapper::class)->contentPayload($listing->load(['property', 'photos', 'listingRooms']));
        $this->assertSame([
            [
                'roomType' => 'bedroom',
                'roomNumber' => 1,
                'roomName' => 'Primary bedroom',
                'beds' => [['bedType' => 'queen_bed', 'quantity' => 1]],
                'isPrivate' => true,
            ],
        ], $payload['rooms']);
    }


    public function test_airbnb_status_refresh_uses_listing_publish_status_and_stores_locked_fields(): void
    {
        config(['repull.enabled' => true, 'repull.api_key' => 'sk_live_test', 'repull.base_url' => 'https://api.repull.dev']);
        Http::fake([
            'api.repull.dev/v1/channels/airbnb/listings' => Http::response([
                'data' => [[
                    'listingId' => 'repull-4118',
                    'connections' => [[
                        'id' => '55', 'airbnbId' => '1693389202618766851',
                        'accountId' => '112233', 'active' => true, 'primary' => true,
                    ]],
                ]],
            ]),
            'api.repull.dev/v1/listings/repull-4118/publish-status' => Http::response([
                'connections' => [[
                    'connectionId' => '55', 'channel' => 'airbnb',
                    'connected' => true, 'syncEnabled' => false,
                    'lockedFields' => ['name', 'property_type_category'],
                ]],
            ]),
        ]);

        $owner = $this->owner();
        $listing = $this->listingFor($owner);
        $listing->forceFill(['repull_listing_id' => 'repull-4118'])->save();
        $connection = ChannelConnection::create([
            'owner_id' => $owner->id, 'provider' => 'repull', 'channel' => 'airbnb',
            'external_account_id' => '112233', 'status' => 'connected',
            'meta' => ['connection_id' => '55'],
        ]);
        $link = ListingChannelLink::create([
            'listing_id' => $listing->id, 'channel' => 'airbnb',
            'connection_id' => $connection->id, 'state' => 'live',
        ]);

        (new RefreshChannelStatus($owner->id))->handle(app(\App\Services\Repull\RepullClient::class));

        $fresh = $link->fresh();
        $this->assertFalse($fresh->writable);
        $this->assertSame('none', $fresh->sync_category);
        $this->assertContains('name', $fresh->locked_fields);
        $this->assertNotNull($fresh->status_checked_at);
    }

}
