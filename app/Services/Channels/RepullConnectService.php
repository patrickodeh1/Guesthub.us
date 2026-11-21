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
    private const CONNECT_PROVIDERS = ['airbnb' => 'airbnb', 'booking' => 'booking', 'vrbo' => 'vrbo'];

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
    public function start(User $owner, array $channels, string $accessType = 'full_access', string $mode = 'standard'): string
    {
        $channels = collect($channels)->unique()->values();
        if ($channels->count() !== 1 || ! in_array($channels->first(), ['airbnb', 'booking', 'vrbo'], true)) {
            throw new RepullException('Connect one supported channel at a time.', 'invalid_request');
        }

        $channel = (string) $channels->first();
        $state = $this->stateFor($owner);
        $redirectUrl = route('admin.channels.connected', ['state' => $state]);

        if ($channel === 'airbnb') {
            if (! in_array($accessType, ['full_access', 'messaging', 'read_only'], true)) {
                throw new RepullException('Choose a valid Airbnb access level.', 'invalid_request');
            }
            $res = $this->client->createChannelConnectSession('airbnb', [
                'redirectUrl' => $redirectUrl,
                'accessType' => $effectiveAccessType,
            ]);
        } elseif ($channel === 'booking' && $mode === 'extranet') {
            $res = $this->client->createConnectSession([
                'redirectUrl' => $redirectUrl,
                'state' => $state,
                'allowedProviders' => ['booking-extranet-login'],
                ]);
        } elseif ($channel === 'booking') {
            $res = $this->client->createChannelConnectSession('booking', [
                'redirectUrl' => $redirectUrl,
            ]);
        } else {
            if (! in_array($accessType, ['full_access', 'messaging'], true)) {
                throw new RepullException('Choose a valid Vrbo access level.', 'invalid_request');
            }
            // Vrbo uses the multi-provider Connect flow and the vrbo-login provider id.
            $res = $this->client->createConnectSession([
                'redirectUrl' => $redirectUrl,
                'state' => $state,
                'allowedProviders' => ['vrbo-login'],
                'accessType' => $accessType,
            ]);
        }

        $url = (string) ($res['url'] ?? data_get($res, 'data.url') ?? '');
        if ($url === '') {
            throw new RepullException('Repull did not return a Connect URL.', 'invalid_response');
        }

        return $url;
    }


    public function confirmConnection(
        int $ownerId,
        string $provider,
        string $externalAccountId,
        ?string $redirectConnectionId = null,
        ?string $redirectAccessType = null
    ): ChannelConnection {
        $provider = strtolower($provider);
        $channel = $this->channelFromProvider($provider);
        if (! $channel || $externalAccountId === '') {
            throw new RepullException('Repull returned an incomplete channel connection.', 'invalid_response');
        }

        if ($provider === 'booking') {
            $details = $this->client->request('GET', '/v1/connect/booking');
            if ((data_get($details, 'connected') ?? data_get($details, 'data.connected')) !== true) {
                throw new RepullException('Repull has not confirmed the Booking.com connection yet.', 'connection_not_confirmed');
            }

            return $this->recordConnection($ownerId, 'booking', $externalAccountId, [
                'connection_id' => $redirectConnectionId,
                'access_type' => 'full_access',
                'display_name' => 'Booking.com '.$externalAccountId,
                'provider' => $provider,
            ]);
        }

        if ($provider === 'booking-extranet-login') {
            // Extranet login is a messaging-only companion connection. The signed
            // redirect state binds the returned account to this owner; the webhook
            // supplies richer account metadata when it arrives.
            return $this->recordConnection($ownerId, 'booking', $externalAccountId, [
                'connection_id' => $redirectConnectionId,
                'access_type' => 'messaging',
                'display_name' => 'Booking.com Extranet',
                'provider' => $provider,
            ]);
        }

        $details = $this->client->request('GET', '/v1/connect/'.rawurlencode($provider));
        $accounts = (array) (data_get($details, 'accounts') ?? data_get($details, 'data.accounts') ?? []);
        $account = collect($accounts)->first(fn ($item) => (string) ($item['externalAccountId'] ?? $item['accountId'] ?? '') === $externalAccountId);

        if ($provider === 'airbnb' && ! $account) {
            throw new RepullException('Repull did not confirm the connected Airbnb account.', 'connection_not_confirmed');
        }
        if ($provider === 'vrbo-login') {
            $connected = data_get($details, 'connected') ?? data_get($details, 'data.connected');
            if ($connected === false) {
                throw new RepullException('Repull has not confirmed the Vrbo connection yet.', 'connection_not_confirmed');
            }
        }

        return $this->recordConnection($ownerId, $channel, $externalAccountId, [
            'connection_id' => $account['connectionId'] ?? data_get($details, 'connectionId') ?? data_get($details, 'id') ?? $redirectConnectionId,
            'access_type' => $account['accessType'] ?? data_get($details, 'accessType') ?? $redirectAccessType,
            'display_name' => $account['name'] ?? $account['displayName'] ?? null,
            'provider' => $provider,
        ]);
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
