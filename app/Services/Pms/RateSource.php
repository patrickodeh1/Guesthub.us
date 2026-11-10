<?php

namespace App\Services\Pms;

use App\Models\Property;

interface RateSource
{
    /**
     * @return array{
     *     status:string,
     *     message:string,
     *     refreshed_at:?string,
     *     rates:array<int,array{date:string,rate:float,min_stay:?int}>
     * }
     */
    public function fetch(Property $property, bool $force = false): array;

    public function provider(): string;
}
