<?php

namespace App\Jobs;

use App\Models\ChannelConnection;
use App\Models\PropertyListing;
use App\Services\Repull\RepullClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshChannelStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $ownerId) {}

    public function handle(RepullClient $client): void
    {
        $response = $client->getAirbnbListings();
        $remoteListings = (array) data_get($response, 'data', []);

        foreach ($remoteListings as $remote) {
            $repullId = (string) ($remote['listingId'] ?? '');
            if ($repullId === '') {
                continue;
            }

            $listing = PropertyListing::query()
                ->where('repull_listing_id', $repullId)
                ->whereHas('property', fn ($query) => $query->where('owner_id', $this->ownerId))
                ->first();

            if (! $listing) {
                continue;
            }

            // The collection endpoint identifies Airbnb ids and connection ids.
            // Per-listing API sync/writability and locked fields live on publish-status.
            $publishStatus = $client->getPublishStatus($repullId);
            $statusConnections = (array) (data_get($publishStatus, 'connections') ?? data_get($publishStatus, 'data.connections') ?? []);

            foreach ((array) ($remote['connections'] ?? []) as $remoteConnection) {
                $accountId = (string) ($remoteConnection['accountId'] ?? '');
                $connectionId = (string) ($remoteConnection['id'] ?? '');
                if ($accountId === '') {
                    continue;
                }

                $connection = ChannelConnection::query()
                    ->where('owner_id', $this->ownerId)
                    ->where('channel', 'airbnb')
                    ->where('external_account_id', $accountId)
                    ->where('status', 'connected')
                    ->first();

                if (! $connection) {
                    continue;
                }

                $status = collect($statusConnections)->first(function ($item) use ($connectionId, $connection) {
                    $statusId = (string) ($item['connectionId'] ?? $item['id'] ?? '');
                    return ($connectionId !== '' && $statusId === $connectionId)
                        || (string) ($item['externalAccountId'] ?? '') === (string) $connection->external_account_id;
                });

                $syncEnabled = is_array($status) && array_key_exists('syncEnabled', $status)
                    ? (bool) $status['syncEnabled']
                    : (is_array($status) && array_key_exists('writable', $status) ? (bool) $status['writable'] : null);
                $syncCategory = is_array($status) ? ($status['syncCategory'] ?? null) : null;
                if ($syncCategory === null && $syncEnabled !== null) {
                    $syncCategory = $syncEnabled ? 'full' : 'none';
                }

                $link = $listing->channelLinks()->firstOrNew(['channel' => 'airbnb']);
                $link->forceFill([
                    'connection_id' => $connection->id,
                    'external_listing_id' => (string) ($remoteConnection['airbnbId'] ?? $link->external_listing_id ?? ''),
                    'sync_category' => $syncCategory !== null ? (string) $syncCategory : null,
                    'writable' => $syncEnabled,
                    'locked_fields' => is_array($status) ? (array) ($status['lockedFields'] ?? []) : $link->locked_fields,
                    'status_checked_at' => now(),
                ])->save();
            }
        }
    }
}
