<?php

namespace App\Services\Channels;

use App\Models\ChannelConnection;
use App\Models\ChannelSyncEvent;
use App\Models\ListingChannelLink;
use App\Models\PropertyListing;
use App\Services\Repull\RepullClient;
use App\Services\Repull\RepullException;

/**
 * Orchestrates "edit once in GuestHub -> push to the OTAs" through Repull.
 *
 * Flow: ensure a Repull listing -> write canonical content (local to Repull) ->
 * publish to a channel -> record the PER-SECTION result. A publish is up to
 * eight independent channel calls and is never one boolean: partial success and
 * "200 but nothing applied" (locked fields) are normal and are surfaced to the host.
 */
class RepullListingService
{
    /** Channels the UI knows about. */
    public const CHANNELS = ['airbnb', 'booking', 'vrbo'];

    /** Channels GuestHub can publish to today. Vrbo is connect-only until verified (plan task T4.7). */
    public const PUBLISHABLE = ['airbnb', 'booking'];

    public function __construct(private RepullClient $client, private ListingContentMapper $mapper)
    {
    }

    /** @return array<string,array{connect:bool,publish:bool,note:string}> */
    public function capabilities(): array
    {
        return [
            'airbnb' => ['connect' => true, 'publish' => true, 'note' => 'Create and update listings, photos, descriptions, rates and calendar. Some fields on an existing Airbnb listing are host-managed and cannot be changed from here.'],
            'booking' => ['connect' => true, 'publish' => true, 'note' => 'Creates the property and room, then pushes content. Booking.com opens it only after its readiness check passes (photos processed, availability, licence where required).'],
            'vrbo' => ['connect' => true, 'publish' => false, 'note' => 'Connects through the host\'s own Vrbo login. Listing content pushes for Vrbo are not enabled yet.'],
        ];
    }

    public function linkFor(PropertyListing $listing, string $channel): ListingChannelLink
    {
        return ListingChannelLink::firstOrCreate(
            ['listing_id' => $listing->id, 'channel' => $channel],
            ['state' => 'not_connected'],
        );
    }

    /** The host's own connected account for this channel (never another host's). */
    public function connectionFor(PropertyListing $listing, string $channel): ?ChannelConnection
    {
        $ownerId = $listing->property?->owner_id;
        if (! $ownerId) {
            return null;
        }

        return ChannelConnection::query()
            ->where('owner_id', $ownerId)
            ->where('provider', 'repull')
            ->where('channel', $channel)
            ->where('status', 'connected')
            ->where('access_type', 'full_access')
            ->orderByDesc('connected_at')
            ->first();
    }

    public function ensureRemoteListing(PropertyListing $listing): string
    {
        if (filled($listing->repull_listing_id)) {
            return (string) $listing->repull_listing_id;
        }

        $res = $this->client->createListing($this->mapper->createPayload($listing), 'guesthub-listing-'.$listing->id.'-create');
        $id = (string) (data_get($res, 'id') ?? data_get($res, 'data.id') ?? '');
        if ($id === '') {
            throw new RepullException('Repull did not return a listing id.', 'invalid_response');
        }

        $listing->forceFill(['repull_listing_id' => $id])->save();
        $this->event($listing, null, 'create_remote_listing', 'ok', null, ['repull_listing_id' => $id]);

        return $id;
    }

    /** @return array{changed:array,deferred:array} */
    public function pushContent(PropertyListing $listing): array
    {
        $id = $this->ensureRemoteListing($listing);
        $res = $this->client->updateListingContent($id, $this->mapper->contentPayload($listing));
        $out = ['changed' => (array) ($res['changed'] ?? []), 'deferred' => (array) ($res['deferred'] ?? [])];
        $this->event($listing, null, 'push_content', 'ok', null, $out);

        return $out;
    }

    /**
     * @throws ChannelPublishBlocked when GuestHub refuses to attempt it (record it via recordBlocked)
     * @throws RepullException only when the failure is retryable (after recording it on the link)
     */
    public function publish(PropertyListing $listing, string $channel, bool $force = false, ?string $hotelId = null): ListingChannelLink
    {
        if (! in_array($channel, self::PUBLISHABLE, true)) {
            throw new ChannelPublishBlocked("Publishing to {$channel} is not enabled yet.");
        }

        $missing = $this->mapper->missing($listing, $channel);
        if ($missing !== []) {
            throw new ChannelPublishBlocked('Complete the listing first: '.implode(', ', $missing).'.', $missing);
        }

        $connection = $this->connectionFor($listing, $channel);
        if (! $connection) {
            throw new ChannelPublishBlocked('Connect the host\'s '.ucfirst($channel).' account with full management access before publishing. Messaging-only and read-only connections cannot change listing content or calendars.');
        }

        $link = $this->linkFor($listing, $channel);
        $link->forceFill(['state' => 'publishing', 'connection_id' => $connection->id, 'last_error' => null, 'last_error_at' => null])->save();

        try {
            $remoteId = $this->ensureRemoteListing($listing);
            $this->pushContent($listing);
            $hash = $this->mapper->hash($listing);
            $idem = 'guesthub-pub-'.$channel.'-'.$listing->id.'-'.substr($hash, 0, 16).($force ? '-f'.now()->timestamp : '');

            $result = $channel === 'airbnb'
                ? $this->publishAirbnb($link, $connection, $remoteId, $force, $idem)
                : $this->publishBooking($listing, $link, $remoteId, $idem, $hotelId);

            $this->applyResult($listing, $link, $channel, $result, $hash);
        } catch (RepullException $e) {
            $this->fail($listing, $link, $channel, $e);
            if ($e->retryable) {
                throw $e;
            }
        }

        return $link->fresh();
    }

    public function recordBlocked(PropertyListing $listing, string $channel, ChannelPublishBlocked $e): void
    {
        $link = $this->linkFor($listing, $channel);
        $link->forceFill(['state' => 'error', 'last_error' => $e->getMessage(), 'last_error_at' => now()])->save();
        $this->event($listing, $link, 'publish', 'blocked', null, ['reason' => $e->getMessage(), 'missing' => array_keys($e->missing)]);
    }

    /** Ask Repull whether the listing is ready (address readiness, per-channel push status, locked fields). */
    public function readiness(PropertyListing $listing): array
    {
        if (! filled($listing->repull_listing_id)) {
            return [];
        }

        return $this->client->getPublishStatus((string) $listing->repull_listing_id);
    }

    /**
     * Take the Repull listing offline/online across every connected channel.
     * Every channel result is applied independently; one failure never hides
     * another channel's success.
     *
     * @return array{ok:bool,channels:array<int,array<string,mixed>>}
     */
    public function setListingOnline(PropertyListing $listing, bool $online): array
    {
        if (! filled($listing->repull_listing_id)) {
            throw new ChannelPublishBlocked('This listing has not been created in Repull yet.');
        }

        $action = $online ? 'online' : 'offline';
        $response = $this->client->request(
            'POST',
            '/v1/listings/'.rawurlencode((string) $listing->repull_listing_id).'/'.$action
        );
        $channels = (array) (data_get($response, 'channels') ?? data_get($response, 'data.channels') ?? []);
        if ($channels === []) {
            throw new RepullException('Repull did not return per-channel results for the '.$action.' request.', 'invalid_response');
        }

        $groups = collect($channels)->groupBy(fn ($result) => strtolower((string) ($result['channel'] ?? $result['platform'] ?? '')));
        $processed = 0;
        $allOk = true;

        foreach ($groups as $channel => $entries) {
            if (! in_array($channel, self::CHANNELS, true)) {
                continue;
            }
            $processed++;
            $channelOk = $entries->every(fn ($result) => ($result['ok'] ?? false) === true);
            $allOk = $allOk && $channelOk;
            $messages = $entries->filter(fn ($result) => ($result['ok'] ?? false) !== true)
                ->map(fn ($result) => trim((string) ($result['message'] ?? $result['fix'] ?? $result['code'] ?? 'Channel action was not confirmed.')))
                ->filter()->unique()->values()->all();

            $link = $this->linkFor($listing, $channel);
            $link->forceFill([
                'state' => $channelOk ? ($online ? 'published' : 'unlisted') : $link->state,
                'live' => $channelOk ? ($online ? null : false) : $link->live,
                'last_synced_at' => $channelOk ? now() : $link->last_synced_at,
                'last_error' => $channelOk ? null : implode('; ', $messages),
                'last_error_at' => $channelOk ? null : now(),
            ])->save();

            $this->event($listing, $link, $action, $channelOk ? 'ok' : 'error', null, [
                'ok' => $channelOk,
                'results' => $entries->values()->all(),
            ]);
        }

        if ($processed === 0) {
            throw new RepullException('Repull returned no recognized channel results for the '.$action.' request.', 'invalid_response');
        }

        return ['ok' => $allOk, 'channels' => $channels];
    }

    public function unlist(PropertyListing $listing, string $channel): ListingChannelLink
    {
        $link = $this->linkFor($listing, $channel);
        $connection = $this->connectionFor($listing, $channel);
        if (! $connection || ! filled($listing->repull_listing_id)) {
            throw new ChannelPublishBlocked('This listing is not published to '.ucfirst($channel).'.');
        }
        if ($connection->access_type !== 'full_access') {
            throw new ChannelPublishBlocked('This account is connected in messaging/read-only mode. Reconnect with full management access before unlisting.');
        }

        try {
            if ($channel === 'airbnb') {
                $this->client->airbnbListingAction((string) $listing->repull_listing_id, array_filter([
                    'action' => 'unlist',
                    'airbnbConnectionId' => $connection->meta['connection_id'] ?? null,
                ]));
            } elseif ($channel === 'booking' && filled($link->external_listing_id)) {
                $this->client->bookingPropertyAction((string) $link->external_listing_id, ['action' => 'unlist']);
            } else {
                throw new ChannelPublishBlocked('Unlisting is not available for '.ucfirst($channel).'.');
            }
            $link->forceFill(['state' => 'unlisted', 'live' => false, 'last_synced_at' => now(), 'last_error' => null])->save();
            $this->event($listing, $link, 'unlist', 'ok');
        } catch (RepullException $e) {
            $this->fail($listing, $link, $channel, $e);
            // Never tell the host the listing was taken offline when Repull rejected it.
            // Retryable errors are handled by the caller/queue; non-retryable errors
            // must also reach the UI instead of being silently converted to success.
            throw $e;
        }

        return $link->fresh();
    }

    // ── internals ───────────────────────────────────────────────────────────

    private function publishAirbnb(ListingChannelLink $link, ChannelConnection $connection, string $remoteId, bool $force, string $idem): array
    {
        if (blank($link->external_listing_id)) {
            // First publish: create a NEW Airbnb listing under the host's account (needs a full_access connection).
            $body = ['hostId' => (string) $connection->external_account_id];
        } else {
            $connectionId = $connection->meta['connection_id'] ?? null;
            if (blank($connectionId)) {
                throw new ChannelPublishBlocked('Airbnb connection id is missing; reconnect the Airbnb account.');
            }
            $body = ['airbnbConnectionId' => (string) $connectionId];
        }
        if ($force) {
            $body['force'] = true;
        }

        $res = $this->client->publishAirbnb($remoteId, $body, $idem);

        return is_array(data_get($res, 'result')) ? data_get($res, 'result') : (is_array(data_get($res, 'data.result')) ? data_get($res, 'data.result') : $res);
    }

    private function publishBooking(PropertyListing $listing, ListingChannelLink $link, string $remoteId, string $idem, ?string $hotelId = null): array
    {
        if (blank($link->external_listing_id)) {
            $p = $listing->property;
            $setup = $this->client->bookingSetup(array_filter([
                'action' => 'create-property',
                'listing_id' => $remoteId,
                'contact' => array_filter([
                    'name' => $p?->owner?->name,
                    'email' => $p?->contact_email ?: $p?->owner?->email,
                    'phone' => $p?->contact_phone,
                ]),
            ]), $idem.'-setup');
            $propertyId = (string) (data_get($setup, 'propertyId') ?? data_get($setup, 'data.propertyId') ?? '');
            if ($propertyId === '') {
                throw new RepullException('Booking.com property was not created.', 'invalid_response', 0, false, $setup);
            }
            $link->forceFill(['external_listing_id' => $propertyId])->save();

            // create-property creates the first room and its rate product. Persist
            // its ids so retries never create duplicate Booking.com rooms.
            $configuredRooms = $listing->rooms_sync_enabled
                ? $listing->listingRooms()->orderBy('room_number')->orderBy('id')->get()
                : collect();
            $firstRoom = $configuredRooms->first();
            if ($firstRoom && filled(data_get($setup, 'roomId') ?? data_get($setup, 'data.roomId'))) {
                $firstRoom->forceFill([
                    'booking_room_id' => (string) (data_get($setup, 'roomId') ?? data_get($setup, 'data.roomId')),
                    'booking_rate_id' => filled(data_get($setup, 'rateId') ?? data_get($setup, 'data.rateId'))
                        ? (string) (data_get($setup, 'rateId') ?? data_get($setup, 'data.rateId'))
                        : null,
                ])->save();
            }
        }

        // Booking.com is a property → room → rate-product hierarchy. A listing
        // with more than one configured room must add each extra room and verify
        // that Repull created its rate product before trying to publish.
        if ($listing->rooms_sync_enabled) {
            $configuredRooms = $listing->listingRooms()->orderBy('room_number')->orderBy('id')->get();
            foreach ($configuredRooms->skip(1) as $room) {
                if (filled($room->booking_room_id) && filled($room->booking_rate_id)) {
                    continue;
                }

                $added = $this->client->bookingSetup([
                    'action' => 'add-room',
                    'property_id' => (string) $link->external_listing_id,
                    'listing_id' => $remoteId,
                ], $idem.'-room-'.$room->id);
                $roomId = data_get($added, 'roomId') ?? data_get($added, 'data.roomId');
                $rateId = data_get($added, 'rateId') ?? data_get($added, 'data.rateId');
                if (! filled($roomId) || ! filled($rateId)) {
                    throw new ChannelPublishBlocked(
                        'Booking.com room '.$room->room_number.' was created without an active rate product. Complete its rate setup in Booking.com, then retry.'
                    );
                }
                $room->forceFill([
                    'booking_room_id' => (string) $roomId,
                    'booking_rate_id' => (string) $rateId,
                ])->save();
            }
        }

        $advance = $this->client->bookingSetup([
            'action' => 'advance',
            'property_id' => (string) $link->external_listing_id,
        ], $idem.'-advance');
        $sellable = data_get($advance, 'sellable') ?? data_get($advance, 'data.sellable');
        $blockers = (array) (data_get($advance, 'blockers') ?? data_get($advance, 'data.blockers') ?? []);
        if ($sellable !== true) {
            $detail = $blockers !== [] ? implode('; ', array_map('strval', $blockers)) : 'Booking.com readiness has not confirmed that the property is sellable yet.';
            throw new ChannelPublishBlocked('Booking.com is not ready to publish: '.$detail);
        }

        $res = $this->client->publishBooking($remoteId, ['hotelId' => (string) ($hotelId ?: $link->external_listing_id)], $idem);

        return is_array($res['result'] ?? null) ? $res['result'] : $res;
    }

    private function applyResult(PropertyListing $listing, ListingChannelLink $link, string $channel, array $result, string $hash): void
    {
        $errors = $this->flatten($result['errors'] ?? []);
        $warnings = array_merge($this->flatten($result['warnings'] ?? []), $this->mapper->warnings($listing));
        $sections = $this->flatten($result['sections'] ?? []);
        $locked = $this->flatten($result['lockedFields'] ?? []);
        // Booking.com's response has no `published` flag: HTTP success with no errors counts as published.
        $published = (bool) ($result['published'] ?? ($channel === 'booking' && $errors === []));
        $live = $result['live'] ?? null; // absent means "not asked", NOT false

        $state = match (true) {
            $published && $live === true => 'live',
            $published && $errors === [] => 'published',
            $sections !== [] && $errors !== [] => 'partial',
            default => 'error',
        };

        $link->forceFill([
            'state' => $state,
            'live' => is_bool($live) ? $live : null,
            'content_hash' => $errors === [] ? $hash : $link->content_hash,
            'last_synced_at' => now(),
            'last_error' => $errors === [] ? null : implode('; ', $errors),
            'last_error_at' => $errors === [] ? null : now(),
            'sections' => $sections,
            'locked_fields' => $locked,
            'warnings' => $warnings,
        ])->save();

        $this->event($listing, $link, 'publish', $errors === [] ? 'ok' : 'error', null, [
            'state' => $state, 'sections' => $sections, 'errors' => $errors, 'locked' => $locked,
        ]);
    }

    private function fail(PropertyListing $listing, ListingChannelLink $link, string $channel, RepullException $e): void
    {
        // Message is the channel's own free text: store/render it, never branch on it.
        $link->forceFill(['state' => 'error', 'last_error' => '['.$e->errorCode.'] '.$e->getMessage(), 'last_error_at' => now()])->save();
        $this->event($listing, $link, 'publish', 'error', $e->httpStatus ?: null, ['code' => $e->errorCode, 'retryable' => $e->retryable]);
    }

    /** @return list<string> */
    private function flatten(mixed $items): array
    {
        $out = [];
        foreach ((array) $items as $item) {
            if (is_string($item)) {
                $out[] = $item;
            } elseif (is_array($item)) {
                $label = (string) ($item['section'] ?? $item['code'] ?? '');
                $msg = (string) ($item['message'] ?? $item['error'] ?? json_encode($item));
                $out[] = trim($label !== '' ? "{$label}: {$msg}" : $msg);
            }
        }

        return $out;
    }

    private function event(PropertyListing $listing, ?ListingChannelLink $link, string $action, string $status, ?int $http = null, array $summary = []): void
    {
        ChannelSyncEvent::create([
            'listing_channel_link_id' => $link?->id,
            'listing_id' => $listing->id,
            'owner_id' => $listing->property?->owner_id,
            'channel' => $link?->channel,
            'action' => $action,
            'status' => $status,
            'http_status' => $http,
            'summary' => $summary ?: null,
        ]);
    }
}