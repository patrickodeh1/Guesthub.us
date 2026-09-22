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
    ) {}

    /**
     * Nothing could be read at all (API not configured, call failed, or
     * empty response). Distinct from "read the document but couldn't find
     * a given field" — both end up routed to manual review by the caller,
     * but this is useful to log/debug separately.
     */
    public static function unavailable(): self
    {
        return new self;
    }

    public function age(): ?int
    {
        return $this->dateOfBirth?->age;
    }

    public function isExpired(): bool
    {
        return $this->expiryDate !== null && $this->expiryDate->isPast();
    }

    public function hasUsableName(): bool
    {
        return filled($this->name);
    }
}
