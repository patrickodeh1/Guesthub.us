<?php

namespace App\Services\Channels;

use RuntimeException;

/** A publish that GuestHub refuses to attempt (missing fields, no connection, unsupported channel). */
class ChannelPublishBlocked extends RuntimeException
{
    /** @param array<string,string> $missing field => human label */
    public function __construct(string $message, public readonly array $missing = [])
    {
        parent::__construct($message);
    }
}
