<?php

namespace App\Services\Channels;

use App\Models\ChannelConnection;
use App\Models\User;
use App\Services\Repull\RepullClient;
use App\Services\Repull\RepullException;

/**
 * Hosted Connect: each host connects THEIR OWN Airbnb / Booking.com / Vrbo account
 * into GuestHub's single Repull workspace. The `state` we pass is HMAC-signed so a
 * webhook can only attach an account to the owner we actually sent through Connect.
 */
class RepullConnectService
{
    /** GuestHub channel => Repull Connect provider id. */
    private const CONNECT_PROVIDERS = ['airbnb' => 'airbnb', 'booking' => 'booking', 'vrbo' => 'vrbo-login'];

    /** Repull provider/account.provider value => GuestHub channel. */
    private const CHANNEL_FROM_PROVIDER = [
        'airbnb' => 'airbnb',
        'booking' => 'booking', 'booking.com' => 'booking', 'booking-extranet-login' => 'booking',
        'vrbo' => 'vrbo', 'vrbo-login' => 'vrbo',
    ];

    public function __construct(private RepullClient $client)
    {
    }

    public function stateFor(User $owner): string
    {
        return $owner->id.'.'.substr(hash_hmac('sha256', 'owner:'.$owner->id, (string) config('app.key')), 0, 32);
    }

    public function ownerIdFromState(?string $state): ?int
    {
        if (! is_string($state) || ! preg_match('/\A(\d+)\.([a-f0-9]{32})\z/', $state, $m)) {
            return null;
        }

        $expected = substr(hash_hmac('sha256', 'owner:'.$m[1], (string) config('app.key')), 0, 32);

        return hash_equals($expected, $m[2]) ? (int) $m[1] : null;
    }

    public function channelFromProvider(?string $provider): ?string
    {
        return self::CHANNEL_FROM_PROVIDER[strtolower((string) $provider)] ?? null;
    }

    /**
     * Mint a Connect session and return the hosted URL to redirect the host to.
     *
     * @param list<string> $channels subset of airbnb|booking|vrbo
     * @throws RepullException
     */
    public function start(User $owner, array $channels): string
    {
        $providers = collect($channels)->map(fn ($c) => self::CONNECT_PROVIDERS[$c] ?? null)->filter()->values()->all();
        if ($providers === []) {
            throw new RepullException('No supported channel selected.', 'invalid_request');
        }

        $res = $this->client->createConnectSession([
            'redirectUrl' => route('admin.channels.connected'),
            'state' => $this->stateFor($owner),
            'allowedProviders' => $providers,
        ]);

        $url = (string) ($res['url'] ?? '');
        if ($url === '') {
            throw new RepullException('Repull did not return a Connect URL.', 'invalid_response');
        }

        return $url;
    }

    public function recordConnection(int $ownerId, string $channel, string $externalAccountId, array $meta = []): ChannelConnection
    {
        $connection = ChannelConnection::firstOrNew([
            'provider' => 'repull', 'channel' => $channel, 'external_account_id' => $externalAccountId,
        ]);

        // An account that is already attached to a DIFFERENT host is never silently re-assigned.
        if ($connection->exists && (int) $connection->owner_id !== $ownerId) {
            throw new RepullException('This channel account is already connected to another host.', 'account_conflict');
        }

        $accessType = $meta['access_type'] ?? $connection->access_type;
        $displayName = $meta['display_name'] ?? $connection->display_name;
        unset($meta['access_type'], $meta['display_name']);

        $connection->fill([
            'owner_id' => $ownerId,
            'status' => 'connected',
            'access_type' => $accessType,
            'display_name' => $displayName,
            'connected_at' => $connection->connected_at ?? now(),
            'last_error' => null,
            'meta' => array_merge($connection->meta ?? [], array_filter($meta, fn ($v) => $v !== null && $v !== '')),
        ])->save();

        return $connection;
    }
}
