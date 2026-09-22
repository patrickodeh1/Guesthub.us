<?php

namespace App\Services\Pms;

/**
 * Placeholder for the future NextPax migration. Deliberately unimplemented
 * — this file exists so App\Providers\AppServiceProvider's config-driven
 * binding is real today, not hypothetical. When the client is ready to
 * move off Channex, this becomes the only class that needs writing; no
 * other code in the app should need to change.
 */
class NextPaxProvider implements PmsProviderInterface
{
    public function getBookings(?\DateTimeInterface $since = null): array
    {
        throw new \RuntimeException('NextPaxProvider is not implemented yet.');
    }

    public function getBooking(string $externalBookingId): ?PmsBooking
    {
        throw new \RuntimeException('NextPaxProvider is not implemented yet.');
    }

    public function acknowledgeBooking(string $externalBookingId): void
    {
        throw new \RuntimeException('NextPaxProvider is not implemented yet.');
    }

    public function handleWebhookPayload(array $payload): ?PmsBooking
    {
        throw new \RuntimeException('NextPaxProvider is not implemented yet.');
    }

    public function getAllBookings(?array $dateRange = null): array
    {
        throw new \RuntimeException('NextPaxProvider is not implemented yet.');
    }

    public function getRoomTypes(string $externalPropertyId): array
    {
        throw new \RuntimeException('NextPaxProvider is not implemented yet.');
    }

    public function pushAvailability(string $externalPropertyId, string $roomTypeId, array $dates): bool
    {
        throw new \RuntimeException('NextPaxProvider is not implemented yet.');
    }
}
