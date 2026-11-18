<?php

namespace App\Services;

use Seam\SeamClient;

class SeamService
{
    protected SeamClient $client;

    public function __construct()
    {
        $this->client = new SeamClient(api_key: config('services.seam.api_key'));
    }

    /**
     * Create a host-scoped Seam Connect Webview for the supported lock brands.
     * The returned URL is the only Seam value sent to the browser.
     */
    public function createConnectWebview(User $host, string $successUrl, string $failureUrl): array
    {
        $webview = $this->client->connect_webviews->create(
            accepted_providers: ['yale', 'august'],
            customer_key: 'gh-'.$host->id,
            custom_redirect_url: $successUrl,
            custom_redirect_failure_url: $failureUrl,
            wait_for_device_creation: true,
        );

        return $this->toArray($webview);
    }

    public function getConnectWebview(string $connectWebviewId): array
    {
        return $this->toArray($this->client->connect_webviews->get(
            connect_webview_id: $connectWebviewId,
        ));
    }

    /**
     * Devices are always fetched with a connected_account_id, never workspace-wide.
     */
    public function listDevices(string $connectedAccountId): array
    {
        $devices = $this->client->devices->list(
            connected_account_id: $connectedAccountId,
        );

        return array_map(fn ($device) => $this->toArray($device), is_array($devices) ? $devices : iterator_to_array($devices));
    }

    public function findDeviceForHost(User $host, string $deviceId): ?array
    {
        foreach ($host->seamConnectedAccounts()->where('status', 'connected')->get() as $account) {
            foreach ($this->listDevices($account->seam_connected_account_id) as $device) {
                if (($device['device_id'] ?? null) === $deviceId) {
                    return $device;
                }
            }
        }

        return null;
    }

    private function toArray(mixed $resource): array
    {
        if (is_array($resource)) {
            return $resource;
        }

        return json_decode(json_encode($resource, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    public function unlock(string $deviceId): array
    {
        $attempt = $this->client->locks->unlock_door(device_id: $deviceId, wait_for_action_attempt: false);
        $result = (array) $attempt;
        \Illuminate\Support\Facades\Log::info('Seam unlock command sent', ['device_id' => $deviceId, 'response' => $result]);
        return $result;
    }

    public function lock(string $deviceId): array
    {
        $attempt = $this->client->locks->lock_door(device_id: $deviceId, wait_for_action_attempt: false);
        $result = (array) $attempt;
        \Illuminate\Support\Facades\Log::info('Seam lock command sent', ['device_id' => $deviceId, 'response' => $result]);
        return $result;
    }

    public function getDevice(string $deviceId): array
    {
        return $this->toArray($this->client->devices->get(device_id: $deviceId));
    }

    public function getLockStatus(string $deviceId): ?bool
    {
        $device = $this->client->devices->get(device_id: $deviceId);
        return $device->properties->locked ?? null;
    }

    public function getBatteryLevel(string $deviceId): ?int
    {
        $device = $this->client->devices->get(device_id: $deviceId);
        $level = $device->properties->battery->level ?? null;

        if ($level === null || ! is_numeric($level)) {
            return null;
        }

        // Seam normalizes most locks' battery.level to a 0-1 ratio, but some
        // (August included) already report a 0-100 percentage. Multiplying a
        // percentage by 100 was showing absurd values; detect and convert only
        // when it is actually a ratio.
        $level = (float) $level;
        $percent = $level <= 1.001 ? $level * 100 : $level;

        return (int) max(0, min(100, round($percent)));
    }

    public function createGuestAccessGrant(string $deviceId, string $guestName, string $guestEmail, \DateTime $startsAt, \DateTime $endsAt): array
    {
        $grant = $this->client->access_grants->create(
            user_identity: [
                'full_name' => $guestName,
                'email_address' => $guestEmail,
            ],
            device_ids: [$deviceId],
            requested_access_methods: [['mode' => 'code']],
            starts_at: $startsAt->format(DATE_ATOM),
            ends_at: $endsAt->format(DATE_ATOM),
        );

        return (array) $grant;
    }

    public function getAccessMethod(string $accessMethodId): array
    {
        return (array) $this->client->access_methods->get(access_method_id: $accessMethodId);
    }
}
