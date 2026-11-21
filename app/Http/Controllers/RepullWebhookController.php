<?php

namespace App\Http\Controllers;

use App\Models\ChannelConnection;
use App\Models\ListingChannelLink;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Channels\RepullConnectService;
use App\Services\Repull\RepullClient;
use App\Services\Repull\RepullException;
use App\Services\Repull\RepullSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Receives Repull webhooks (HMAC-signed). Always answers 200 for events we choose to
 * ignore so Repull does not retry them for 24h; answers 401/503 only for auth/config problems.
 *
 * Payload keys below follow Repull's documented `account` block (provider + externalAccountId)
 * and the connect.session.completed `state` echo. Exact nesting is verified against a Repull
 * test-fire in plan task T4.3 - the extraction is deliberately tolerant.
 */
class RepullWebhookController extends Controller
{
    public function handle(Request $request, RepullConnectService $connect)
    {
        $secret = (string) config('repull.webhook_secret');
        if ($secret === '') {
            Log::error('Repull webhook rejected: REPULL_WEBHOOK_SECRET is not configured');

            return response()->json(['ok' => false, 'error' => 'Webhook not configured'], 503);
        }

        $raw = $request->getContent();
        if (! RepullSignature::verify($raw, $request->header('X-Repull-Signature'), $secret, (int) config('repull.webhook_tolerance', 300))) {
            ActivityLogService::security('repull_webhook_invalid_signature', 'Rejected a Repull webhook with an invalid or stale signature.', [
                'severity' => 'warning',
            ]);

            return response()->json(['ok' => false, 'error' => 'Invalid signature'], 401);
        }

        // Retries carry the same delivery id; process each delivery once.
        $deliveryId = (string) $request->header('X-Repull-Delivery');
        if ($deliveryId !== '' && ! Cache::add('repull:delivery:'.$deliveryId, 1, now()->addHours(25))) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $payload = RepullClient::decode($raw);
        $event = (string) ($request->header('X-Repull-Event') ?: ($payload['type'] ?? $payload['event'] ?? ''));

        try {
            match ($event) {
                'connect.session.completed', 'account.created' => $this->accountConnected($payload, $request, $connect),
                'account.disconnected' => $this->accountDisconnected($payload, $request, $connect),
                'channel.sync.failed', 'channel.degraded', 'listing.suspended' => $this->channelProblem($payload, $event),
                default => null,
            };
        } catch (RepullException $e) {
            Log::warning('Repull webhook not applied', ['event' => $event, 'code' => $e->errorCode]);
        }

        return response()->json(['ok' => true]);
    }

    private function accountConnected(array $payload, Request $request, RepullConnectService $connect): void
    {
        $ownerId = $connect->ownerIdFromState((string) (data_get($payload, 'data.state') ?? data_get($payload, 'state') ?? ''));
        $channel = $connect->channelFromProvider((string) (data_get($payload, 'account.provider') ?? data_get($payload, 'data.provider') ?? ''));
        $accountId = (string) (data_get($payload, 'account.externalAccountId') ?? $request->header('X-Repull-Account-Id') ?? '');

        if (! $ownerId || ! $channel || $accountId === '') {
            // account.created carries no state: it cannot be attributed to a host, so ignore it.
            return;
        }

        $owner = User::find($ownerId);
        if (! $owner) {
            return;
        }

        $connect->recordConnection($ownerId, $channel, $accountId, [
            'connection_id' => data_get($payload, 'data.connectionId') ?? data_get($payload, 'account.connectionId'),
            'access_type' => data_get($payload, 'data.accessType') ?? data_get($payload, 'account.accessType'),
            'display_name' => data_get($payload, 'account.name') ?? data_get($payload, 'account.displayName'),
        ]);
    }

    private function accountDisconnected(array $payload, Request $request, RepullConnectService $connect): void
    {
        $channel = $connect->channelFromProvider((string) data_get($payload, 'account.provider'));
        $accountId = (string) (data_get($payload, 'account.externalAccountId') ?? $request->header('X-Repull-Account-Id') ?? '');
        if (! $channel || $accountId === '') {
            return;
        }

        ChannelConnection::where(['provider' => 'repull', 'channel' => $channel, 'external_account_id' => $accountId])
            ->update(['status' => 'revoked']);
    }

    private function channelProblem(array $payload, string $event): void
    {
        $listingId = (string) (data_get($payload, 'data.listingId') ?? '');
        if ($listingId === '') {
            return;
        }

        ListingChannelLink::query()
            ->whereHas('listing', fn ($q) => $q->where('repull_listing_id', $listingId))
            ->update(['state' => 'error', 'last_error' => 'Repull reported: '.$event, 'last_error_at' => now()]);
    }
}
