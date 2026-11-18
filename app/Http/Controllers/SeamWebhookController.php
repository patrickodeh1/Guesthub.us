<?php

namespace App\Http\Controllers;

use App\Models\PropertyLock;
use App\Models\SeamConnectedAccount;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Svix\Webhook;
use Svix\Exception\WebhookVerificationException;

class SeamWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = config('services.seam.webhook_secret');

        if (! $secret) {
            report(new \RuntimeException('SEAM_WEBHOOK_SECRET is not configured.'));
            return response()->json(['ok' => false], 500);
        }

        $payload = $request->getContent();
        $headers = [
            'svix-id' => $request->header('svix-id'),
            'svix-timestamp' => $request->header('svix-timestamp'),
            'svix-signature' => $request->header('svix-signature'),
        ];

        try {
            $wh = new Webhook($secret);
            $verified = $wh->verify($payload, $headers);
            $event = is_array($verified) ? $verified : json_decode($verified, true);
        } catch (WebhookVerificationException $e) {
            ActivityLogService::security('seam_webhook_invalid_signature', 'Rejected a Seam webhook with an invalid signature.', [
                'severity' => 'warning',
            ]);
            return response()->json(['ok' => false, 'error' => 'Invalid signature'], 401);
        }

        $eventType = $event['event_type'] ?? null;
        $deviceId = $event['device_id'] ?? null;
        \Illuminate\Support\Facades\Log::info('Seam webhook received', ['event_type' => $eventType, 'action_attempt_id' => $event['action_attempt_id'] ?? null, 'device_id' => $deviceId]);
        \Illuminate\Support\Facades\Log::info('Seam webhook full payload', ['event' => $event]);

        
        // Seam documents these connected-account lifecycle events. A
        // reauthorization request means the host must reconnect; the
        // connected event is emitted when that authorization succeeds.
        $connectedAccountStatuses = [
            'connected_account.reauthorization_requested' => 'needs_reconnect',
            'connected_account.connected' => 'connected',
        ];

        if (isset($connectedAccountStatuses[$eventType])) {
            $connectedAccountId = $event['connected_account_id'] ?? null;

            if (is_string($connectedAccountId) && $connectedAccountId !== '') {
                $connectedAccount = SeamConnectedAccount::query()
                    ->where('seam_connected_account_id', $connectedAccountId)
                    ->first();

                if ($connectedAccount) {
                    $previousStatus = $connectedAccount->status;
                    $newStatus = $connectedAccountStatuses[$eventType];

                    $connectedAccount->forceFill([
                        'status' => $newStatus,
                        'last_synced_at' => now(),
                    ])->save();

                    Log::info('Seam connected account status updated from webhook', [
                        'connected_account_id' => $connectedAccountId,
                        'user_id' => $connectedAccount->user_id,
                        'event_type' => $eventType,
                        'previous_status' => $previousStatus,
                        'status' => $newStatus,
                    ]);
                } else {
                    Log::info('Seam connected account webhook ignored for unknown account', [
                        'connected_account_id' => $connectedAccountId,
                        'event_type' => $eventType,
                    ]);
                }
            }
        }

        if (in_array($eventType, ['lock.locked', 'lock.unlocked'], true) && $deviceId) {
            $lock = PropertyLock::where('seam_device_id', $deviceId)->first();

            if ($lock) {
                $lock->update([
                    'last_known_locked' => $eventType === 'lock.locked',
                    'last_status_at' => now(),
                ]);
            }
        }


        $actionAttemptEvents = [
            'action_attempt.lock_door.succeeded'   => ['locked' => true,  'failed' => false],
            'action_attempt.lock_door.failed'      => ['locked' => null, 'failed' => true],
            'action_attempt.unlock_door.succeeded' => ['locked' => false, 'failed' => false],
            'action_attempt.unlock_door.failed'    => ['locked' => null, 'failed' => true],
        ];

        if (isset($actionAttemptEvents[$eventType])) {
            $actionAttemptId = $event['action_attempt_id'] ?? null;
            $attemptMeta = $actionAttemptId ? \Illuminate\Support\Facades\Cache::get('seam_attempt_lock:'.$actionAttemptId) : null;
            $lockId = is_array($attemptMeta) ? ($attemptMeta['lock_id'] ?? null) : $attemptMeta;
            $guestName = is_array($attemptMeta) ? ($attemptMeta['guest_name'] ?? null) : null;
            $bookingId = is_array($attemptMeta) ? ($attemptMeta['booking_id'] ?? null) : null;
            $lock = $lockId ? PropertyLock::find($lockId) : null;

            if ($lock) {
                $meta = $actionAttemptEvents[$eventType];
                $verb = str_contains($eventType, 'lock_door') ? 'lock' : 'unlock';
                $actor = $guestName ? "Guest {$guestName}" : 'Door';

                if ($meta['failed']) {
                    \Illuminate\Support\Facades\Cache::put('seam_attempt_result:'.$actionAttemptId, [
                        'failed' => true,
                        'locked' => null,
                    ], now()->addMinutes(10));
                    ActivityLogService::guest('door_'.$verb.'_failed', "{$actor} {$verb} command failed for {$lock->label} (confirmed by Seam webhook).", 'guest_portal', [
                        'property_id' => $lock->property_id,
                        'severity'    => 'warning',
                        'metadata'    => ['lock_id' => $lock->id, 'seam_device_id' => $lock->seam_device_id, 'action_attempt_id' => $actionAttemptId, 'booking_id' => $bookingId],
                    ]);
                } else {
                    $lock->update([
                        'last_known_locked' => $meta['locked'],
                        'last_status_at' => now(),
                    ]);
                    \Illuminate\Support\Facades\Cache::put('seam_attempt_result:'.$actionAttemptId, [
                        'failed' => false,
                        'locked' => $meta['locked'],
                    ], now()->addMinutes(10));
                    ActivityLogService::guest('door_'.($verb === 'lock' ? 'locked' : 'unlocked'), ($guestName ? "{$actor} {$verb}ed" : "Door {$verb}ed") . " successfully for {$lock->label} (confirmed by Seam webhook).", 'guest_portal', [
                        'property_id' => $lock->property_id,
                        'metadata'    => ['lock_id' => $lock->id, 'seam_device_id' => $lock->seam_device_id, 'action_attempt_id' => $actionAttemptId, 'booking_id' => $bookingId],
                    ]);
                }
            }
        }
        return response()->json(['ok' => true]);
    }
}
