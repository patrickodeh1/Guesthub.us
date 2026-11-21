<?php

namespace App\Services\Repull;

use RuntimeException;
use Throwable;

/**
 * Any failed Repull call. `errorCode` is Repull's machine code (branch on it,
 * never on the message text, which is the channel's own free text).
 */
class RepullException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'repull_error',
        public readonly int $httpStatus = 0,
        public readonly bool $retryable = false,
        public readonly array $details = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public static function notConfigured(): self
    {
        return new self('Repull is not configured. Set REPULL_ENABLED=true and REPULL_API_KEY.', 'not_configured');
    }
}
