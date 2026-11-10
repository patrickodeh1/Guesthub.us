<?php

namespace App\Services\Pms;

use RuntimeException;

class WheelhouseApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0)
    {
        parent::__construct($message);
    }
}
