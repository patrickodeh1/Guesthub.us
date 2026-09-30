<?php

namespace Tests\Unit;

use App\Helpers\TimezoneHelper;
use Carbon\Carbon;
use Tests\TestCase;

class TimezoneHelperTest extends TestCase
{
    public function test_it_converts_and_formats_timestamps_in_the_requested_timezone(): void
    {
        $timestamp = Carbon::parse('2026-01-15 14:00:00', 'UTC');

        $this->assertSame(
            'Jan 15, 2026 6:00 AM',
            TimezoneHelper::format($timestamp, 'America/Los_Angeles')
        );
        $this->assertSame('UTC', $timestamp->timezoneName);
    }

    public function test_it_returns_the_default_for_a_null_timestamp(): void
    {
        $this->assertSame('--', TimezoneHelper::format(null));
        $this->assertNull(TimezoneHelper::toLocal(null));
    }
}
