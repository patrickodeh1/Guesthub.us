<?php

namespace App\Exceptions;

class ConflictsDetected extends \RuntimeException
{
    private array $conflicts;

    public function __construct(array $conflicts)
    {
        $this->conflicts = $conflicts;
        parent::__construct(collect($conflicts)->pluck('message')->implode(' '));
    }

    public function conflicts(): array
    {
        return $this->conflicts;
    }
}
