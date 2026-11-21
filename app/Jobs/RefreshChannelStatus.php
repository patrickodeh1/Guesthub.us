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
            $details = $client->getAirbnbListingDetails($repullId);
            $detailData = data_get($details, 'data.0') ?? data_get($details, 'data') ?? $details;
            $detailConnections = collect(data_get($detailData, 'connections', []));

            foreach ((array) ($remote['connections'] ?? []) as $remoteConnection) {
                $accountId = (string) ($remoteConnection['accountId'] ?? '');
                $connectionId = (string) ($remoteConnection['id'] ?? '');
                if ($accountId === '') {
                    continue;
                }

                $connection = ChannelConnection::query()
                    ->where('owner_id', $this->ownerId)
                    ->where('provider', 'repull')
                    ->where('channel', 'airbnb')
                    ->where('external_account_id', $accountId)
                    ->where('status', 'connected')
                    ->first();

                if (! $connection) {
                    continue;
                }

                $airbnbId = (string) ($remoteConnection['airbnbId'] ?? '');
                // Details are keyed by the Repull listing id. Match the returned
                // connection record locally; never request details with Airbnb's
                // 19-digit external listing id or another host's connection id.
                $detailConnection = $detailConnections->first(function ($item) use ($connectionId, $accountId) {
                    return ($connectionId !== '' && (string) ($item['id'] ?? $item['connectionId'] ?? '') === $connectionId)
                        || (string) ($item['accountId'] ?? $item['externalAccountId'] ?? '') === $accountId;
                });
                $status = collect($statusConnections)->first(function ($item) use ($connectionId, $connection) {
                    $statusId = (string) ($item['connectionId'] ?? $item['id'] ?? '');
                    return ($connectionId !== '' && $statusId === $connectionId)
                        || (string) ($item['externalAccountId'] ?? '') === (string) $connection->external_account_id;
                });

                $writableValue = $remoteConnection['writable'] ?? data_get($detailConnection, 'writable') ?? data_get($detailConnection, 'syncEnabled') ?? $remote['writable'] ?? (is_array($status) ? ($status['syncEnabled'] ?? $status['writable'] ?? null) : null);
                $syncEnabled = $writableValue !== null ? (bool) $writableValue : null;
                $syncCategory = $remoteConnection['syncCategory'] ?? data_get($detailConnection, 'syncCategory') ?? $remote['syncCategory'] ?? (is_array($status) ? ($status['syncCategory'] ?? null) : null);
                if ($syncCategory === null && $syncEnabled !== null) {
                    $syncCategory = $syncEnabled ? 'full' : 'none';
                }

                $live = data_get($detailConnection, 'live') ?? data_get($detailConnection, 'isLive') ?? data_get($detailData, 'live') ?? data_get($detailData, 'isLive');
                $link = $listing->channelLinks()->firstOrNew(['channel' => 'airbnb']);
                $nextState = $link->state;
                if (in_array($link->state, ['live', 'published'], true) && $live === true) {
                    $nextState = 'live';
                } elseif ($link->state === 'live' && $live === false) {
                    $nextState = 'published';
                }
                $link->forceFill([
                    'state' => $nextState,
                    'connection_id' => $connection->id,
                    'external_listing_id' => (string) ($remoteConnection['airbnbId'] ?? $link->external_listing_id ?? ''),
                    'sync_category' => $syncCategory !== null ? (string) $syncCategory : null,
                    'writable' => $syncEnabled,
                    'locked_fields' => (array) (data_get($detailConnection, 'lockedFields') ?? data_get($detailData, 'lockedFields') ?? (is_array($status) ? ($status['lockedFields'] ?? []) : $link->locked_fields)),
                    'live' => is_bool($live) ? $live : $link->live,
                    'status_checked_at' => now(),
                ])->save();
            }
        }
    }
}
