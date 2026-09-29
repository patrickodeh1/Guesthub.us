<?php

namespace App\Services;

use Carbon\Carbon;

class IdExtractionResult
{
    public function __construct(
        public readonly ?string $rawText = null,
        public readonly ?string $name = null,
        public readonly ?Carbon $dateOfBirth = null,
        public readonly ?Carbon $expiryDate = null,
        // Why nothing could be read (no_api_key, http_403, empty_text, ...).
        // Null when the document was read. Used only for logging/debugging.
        public readonly ?string $failureReason = null,
    ) {}

    /**
     * Nothing could be read at all (API not configured, call failed, or
     * empty response). Distinct from "read the document but couldn't find
     * a given field" — both end up routed to manual review by the caller,
     * but this is useful to log/debug separately.
     */
    public static function unavailable(?string $reason = null): self
    {
        return new self(failureReason: $reason);
    }

    public function age(): ?int
    {
        return $this->dateOfBirth?->age;
    }

    public function isExpired(): bool
    {
        // An ID is valid through its expiry date, so it only counts as
        // expired once that whole day has passed.
        return $this->expiryDate !== null && $this->expiryDate->copy()->endOfDay()->isPast();
    }

    public function hasUsableName(): bool
    {
        return filled($this->name);
    }
}
