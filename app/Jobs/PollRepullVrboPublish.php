<?php

namespace App\Jobs;

use App\Models\ChannelConnection;
use App\Models\ListingChannelLink;
use App\Services\Channels\ListingContentMapper;
use App\Services\Repull\RepullClient;
use App\Services\Repull\RepullException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollRepullVrboPublish implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 30;

    public function __construct(
        public int $linkId,
        public string $repullListingId,
        public string $jobId,
        public string $mode,
        public int $attempt = 0,
    ) {}

    public function handle(RepullClient $client, ListingContentMapper $mapper): void
    {
        $link = ListingChannelLink::query()
            ->with(['listing.property', 'listing.photos'])
            ->find($this->linkId);

        if (! $link || $link->channel !== 'vrbo' || ! config('repull.vrbo_publish_enabled', false)) {
            return;
        }

        $connection = ChannelConnection::query()->find($link->connection_id);
        if (! $connection
            || $connection->provider !== 'repull'
            || $connection->owner_id !== $link->listing?->property?->owner_id
            || $connection->access_type !== 'full_access') {
            $this->failLink($link, 'The full-access Vrbo connection for this listing is no longer available.');
            return;
        }

        try {
            $response = $client->getVrboPublishJob($this->repullListingId, $this->jobId);
        } catch (RepullException $e) {
            if ($e->httpStatus === 404) {
                $this->failLink($link, 'Repull no longer has this Vrbo job. Publish again to resume the unfinished listing.');
                return;
            }
            throw $e;
        }

        $job = data_get($response, 'job') ?? data_get($response, 'data.job') ?? [];
        $status = strtolower((string) data_get($job, 'status', ''));
        $propertyId = data_get($job, 'propertyId') ?? data_get($response, 'propertyId');

        $meta = $link->meta ?? [];
        $meta['vrbo_job_id'] = $this->jobId;
        $meta['vrbo_job_mode'] = $this->mode;
        if (filled($propertyId)) {
            $meta['vrbo_property_id'] = (string) $propertyId;
        }
        if (filled(data_get($job, 'accountId'))) {
            $meta['vrbo_account_id'] = (string) data_get($job, 'accountId');
        }

        if ($status === 'running' || ($status === 'done' && $this->mode === 'create' && ! (bool) data_get($job, 'linked', false))) {
            if ($this->attempt >= 90) {
                $this->failLink($link, 'Vrbo is still processing after 15 minutes. Publish again to resume the same unfinished listing.');
                return;
            }

            $link->forceFill(['meta' => $meta, 'state' => 'publishing'])->save();
            self::dispatch($this->linkId, $this->repullListingId, $this->jobId, $this->mode, $this->attempt + 1)
                ->delay(now()->addSeconds(10));
            return;
        }

        if ($status === 'failed') {
            $this->failLink($link, (string) (data_get($job, 'error') ?: 'Vrbo could not complete the listing publish job.'), $meta);
            return;
        }

        if ($status !== 'done') {
            $this->failLink($link, 'Repull returned an unknown Vrbo job status: '.$status, $meta);
            return;
        }

        $failedSections = collect((array) data_get($job, 'sections', []))
            ->filter(fn ($section) => in_array(strtolower((string) ($section['status'] ?? '')), ['failed', 'rejected'], true));
        $rejectedPhotos = (array) data_get($job, 'rejectedPhotos', []);
        $listingNumber = data_get($job, 'vrboListingNumber') ?? data_get($response, 'vrboListingNumber') ?? $link->external_listing_id;
        $linked = (bool) data_get($job, 'linked', false);
        $live = $this->mode === 'create' ? $linked : (bool) $link->live;
        $state = ($failedSections->isNotEmpty() || $rejectedPhotos !== []) ? 'partial' : ($live ? 'live' : 'published');

        $meta['vrbo_job_status'] = 'done';
        $meta['vrbo_listing_number'] = $listingNumber;
        unset($meta['vrbo_job_id']);

        $errors = [];
        foreach ($failedSections as $section) {
            foreach ((array) ($section['errors'] ?? []) as $error) {
                $errors[] = ($section['section'] ?? 'section').': '.($error['message'] ?? 'Vrbo rejected this section.');
            }
        }
        foreach ($rejectedPhotos as $photo) {
            $errors[] = 'Photo '.($photo['photo'] ?? '?').': '.($photo['reason'] ?? 'rejected by Vrbo');
        }

        $link->forceFill([
            'external_listing_id' => $listingNumber ? (string) $listingNumber : $link->external_listing_id,
            'state' => $state,
            'live' => $live,
            'last_synced_at' => now(),
            'last_error' => $errors !== [] ? implode("\n", $errors) : null,
            'meta' => $meta,
        ])->save();

        $currentHash = $mapper->hash($link->listing);
        if (filled($link->content_hash) && ! hash_equals((string) $link->content_hash, $currentHash)) {
            PublishListingToChannel::dispatch($link->listing_id, 'vrbo')->afterCommit();
        }

        Log::info('Repull Vrbo publish job completed', [
            'link_id' => $link->id,
            'repull_listing_id' => $this->repullListingId,
            'job_id' => $this->jobId,
            'state' => $state,
            'linked' => $linked,
        ]);
    }

    private function failLink(ListingChannelLink $link, string $message, ?array $meta = null): void
    {
        $link->forceFill([
            'state' => 'error',
            'last_error' => $message,
            'meta' => $meta ?? $link->meta,
            'last_synced_at' => now(),
        ])->save();

        Log::warning('Repull Vrbo publish job failed', [
            'link_id' => $link->id,
            'job_id' => $this->jobId,
            'message' => $message,
        ]);
    }
}
