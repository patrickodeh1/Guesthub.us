<?php

namespace App\Jobs;

use App\Models\PropertyListing;
use App\Services\Channels\ListingContentMapper;
use App\Services\Channels\ChannelPublishBlocked;
use App\Services\Channels\RepullListingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Publishes one listing to one channel. Unique per listing x channel; retries only retryable Repull errors. */
class PublishListingToChannel implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $uniqueFor = 600;

    public function __construct(public int $listingId, public string $channel, public bool $force = false, public ?string $hotelId = null)
    {
    }

    public function uniqueId(): string
    {
        return $this->listingId.':'.$this->channel;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(RepullListingService $service, ListingContentMapper $mapper): void
    {
        $listing = PropertyListing::with(['property.owner', 'photos', 'channelLinks'])->find($this->listingId);
        if (! $listing) {
            return;
        }

        // Content-hash no-op: do not call Repull when a prior successful push
        // already contains the current GuestHub content.
        $link = $listing->channelLinks->firstWhere('channel', $this->channel);
        if ($link && $link->state === 'publishing') {
            return;
        }

        if (! $this->force && $link && in_array($link->state, ['published', 'live'], true)
            && filled($link->content_hash) && hash_equals((string) $link->content_hash, $mapper->hash($listing))) {
            return;
        }

        try {
            $service->publish($listing, $this->channel, $this->force, $this->hotelId);
        } catch (ChannelPublishBlocked $e) {
            $service->recordBlocked($listing, $this->channel, $e);
        }
        // RepullException (retryable only) propagates so the queue retries with backoff.
    }
}
