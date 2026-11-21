<?php

namespace Tests\Feature;

use App\Models\ChannelConnection;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\User;
use App\Services\Channels\ListingContentMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertStringNotContainsString('example.com', $create['description']);
        $this->assertStringNotContainsString('555', $create['description']);

        $this->assertSame(['photos' => 'At least one photo'], $mapper->missing($listing, 'airbnb'));
        $this->assertNotEmpty($mapper->warnings($listing));
    }
}
