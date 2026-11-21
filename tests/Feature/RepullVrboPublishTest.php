<?php

namespace Tests\Feature;

use App\Jobs\PollRepullVrboPublish;
use App\Models\ChannelConnection;
use App\Models\ListingChannelLink;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\User;
use App\Services\Channels\ListingContentMapper;
use App\Services\Repull\RepullClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RepullVrboPublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_vrbo_done_and_linked_job_marks_channel_live(): void
    {
        config(['repull.enabled' => true, 'repull.api_key' => 'repull-test', 'repull.base_url' => 'https://api.repull.dev']);
        [$listing, $link] = $this->makePublishingLink();
        $link->forceFill(['content_hash' => app(ListingContentMapper::class)->hash($listing->load('property', 'photos'))])->save();

        Http::fake([
            'api.repull.dev/v1/listings/repull-vrbo-9/publish/vrbo*' => Http::response([
                'job' => [
                    'jobId' => 'job-vrbo-9',
                    'status' => 'done',
                    'mode' => 'create',
                    'linked' => true,
                    'vrboListingNumber' => 'VRBO-9988',
                    'propertyId' => 'property-9988',
                    'sections' => [],
                    'rejectedPhotos' => [],
                ],
            ]),
        ]);

        (new PollRepullVrboPublish($link->id, 'repull-vrbo-9', 'job-vrbo-9', 'create'))->handle(
            app(RepullClient::class),
            app(ListingContentMapper::class),
        );

        $link->refresh();
        $this->assertSame('live', $link->state);
        $this->assertTrue($link->live);
        $this->assertSame('VRBO-9988', $link->external_listing_id);
        $this->assertSame('done', $link->meta['vrbo_job_status']);
    }

    public function test_running_vrbo_job_is_polled_again_after_ten_seconds(): void
    {
        config(['repull.enabled' => true, 'repull.api_key' => 'repull-test', 'repull.base_url' => 'https://api.repull.dev']);
        [, $link] = $this->makePublishingLink();
        Queue::fake();
        Http::fake([
            'api.repull.dev/v1/listings/repull-vrbo-9/publish/vrbo*' => Http::response([
                'job' => ['jobId' => 'job-vrbo-9', 'status' => 'running', 'mode' => 'create', 'propertyId' => 'property-9988'],
            ]),
        ]);

        (new PollRepullVrboPublish($link->id, 'repull-vrbo-9', 'job-vrbo-9', 'create', 0))->handle(
            app(RepullClient::class),
            app(ListingContentMapper::class),
        );

        Queue::assertPushed(PollRepullVrboPublish::class, fn ($job) => $job->attempt === 1 && $job->jobId === 'job-vrbo-9');
        $this->assertSame('publishing', $link->fresh()->state);
    }

    private function makePublishingLink(): array
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'address' => '12 Ocean Drive',
            'city' => 'Miami',
            'state' => 'FL',
            'zip' => '33139',
            'country' => 'US',
            'beds' => 2,
            'baths' => 1,
        ]);
        $listing = PropertyListing::create([
            'property_id' => $property->id,
            'listing_slug' => 'vrbo-test-'.$property->id,
            'public_title' => 'Vrbo Test Listing',
            'public_description' => str_repeat('A comfortable holiday home with great amenities. ', 5),
            'max_guests' => 4,
            'bedrooms' => 2,
            'repull_listing_id' => 'repull-vrbo-9',
            'published_at' => now(),
        ]);
        $connection = ChannelConnection::create([
            'owner_id' => $owner->id,
            'provider' => 'repull',
            'channel' => 'vrbo',
            'external_account_id' => 'vrbo:9988',
            'status' => 'connected',
            'access_type' => 'full_access',
        ]);
        $link = ListingChannelLink::create([
            'listing_id' => $listing->id,
            'channel' => 'vrbo',
            'connection_id' => $connection->id,
            'state' => 'publishing',
            'meta' => ['vrbo_job_id' => 'job-vrbo-9', 'vrbo_job_mode' => 'create'],
        ]);

        return [$listing, $link];
    }
}
