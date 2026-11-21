<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\ListingRoom;
use App\Models\Property;
use App\Models\User;
use App\Services\Channels\ImportFromAirbnbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportFromAirbnbServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_airbnb_import_creates_an_unpublished_guesthub_draft_with_rooms_and_amenities(): void
    {
        config([
            'repull.enabled' => true,
            'repull.api_key' => 'repull-test-key',
            'repull.base_url' => 'https://api.repull.dev',
        ]);
        Http::fake([
            'api.repull.dev/v1/listings/repull-listing-1/pull/airbnb' => Http::response([
                'data' => ['refreshedFromChannel' => true],
            ]),
            'api.repull.dev/v1/listings/repull-listing-1?include=*' => Http::response([
                'data' => [
                    'content' => [
                        'name' => 'Imported Coastal Home',
                        'description' => 'A detailed imported description.',
                        'houseRules' => 'No smoking.',
                        'amenities' => ['wifi', 'kitchen'],
                    ],
                    'details' => [
                        'personCapacity' => 4,
                        'bedrooms' => 2,
                        'beds' => 3,
                        'bathrooms' => 1.5,
                        'propertyTypeCategory' => 'house',
                        'roomTypeCategory' => 'entire_home',
                        'checkInTimeStart' => '16:00',
                        'checkOutTime' => '11:00',
                        'address' => ['street' => '10 Beach Road', 'city' => 'Lagos', 'country' => 'NG'],
                    ],
                    'policies' => ['cancellationPolicy' => 'moderate'],
                    'rooms' => [[
                        'roomType' => 'bedroom',
                        'roomNumber' => 1,
                        'roomName' => 'Main bedroom',
                        'beds' => [
                            ['bedType' => 'queen_bed', 'quantity' => 1],
                            ['bedType' => 'single_bed', 'quantity' => 1],
                        ],
                        'isPrivate' => true,
                    ]],
                    'photos' => [],
                ],
            ]),
        ]);

        $owner = User::factory()->create();
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Coastal Home',
            'beds' => 2,
            'baths' => 1,
        ]);

        $listing = app(ImportFromAirbnbService::class)->importInto($property, 'repull-listing-1', 'connection-55');

        $this->assertSame('Imported Coastal Home', $listing->public_title);
        $this->assertSame('A detailed imported description.', $listing->public_description);
        $this->assertNull($listing->published_at);
        $this->assertSame('repull-listing-1', $listing->repull_listing_id);
        $this->assertSame(3, $property->fresh()->beds);
        $this->assertSame('10 Beach Road', $property->fresh()->address);
        $this->assertTrue($listing->rooms_sync_enabled);
        $this->assertCount(1, ListingRoom::where('listing_id', $listing->id)->get());
        $this->assertCount(2, ListingRoom::where('listing_id', $listing->id)->first()->beds);
        $this->assertDatabaseHas('amenities', [
            'property_id' => $property->id,
            'repull_amenity_id' => 'wifi',
            'active' => 1,
        ]);
    }
}
