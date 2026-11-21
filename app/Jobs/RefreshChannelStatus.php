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
        $response = $client->request('GET', '/v1/channels/airbnb/listings');
        $remoteListings = data_get($response, 'data', []);

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

            foreach ((array) ($remote['connections'] ?? []) as $remoteConnection) {
                $accountId = (string) ($remoteConnection['accountId'] ?? '');
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

                $link = $listing->channelLinks()->firstOrNew(['channel' => 'airbnb']);
                $syncCategory = $remoteConnection['syncCategory'] ?? $remoteConnection['sync_category'] ?? null;
                $writable = array_key_exists('writable', $remoteConnection)
                    ? (bool) $remoteConnection['writable']
                    : (array_key_exists('syncEnabled', $remoteConnection) ? (bool) $remoteConnection['syncEnabled'] : null);

                $link->forceFill([
                    'connection_id' => $connection->id,
                    'external_listing_id' => (string) ($remoteConnection['airbnbId'] ?? $link->external_listing_id ?? ''),
                    'sync_category' => $syncCategory !== null ? (string) $syncCategory : null,
                    'writable' => $writable,
                    'status_checked_at' => now(),
                ])->save();
            }
        }
    }
}
