<?php

namespace Tests\Feature;

use App\Models\CleaningSession;
use App\Models\Property;
use App\Models\User;
use App\Services\CleaningStartWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CleaningStartWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cleaning_start_window_is_read_from_the_property_not_its_owner(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'timezone' => 'UTC',
            'cleaner_start_window_minutes' => 35,
        ]);

        $session = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'housekeeper_id' => User::factory()->create()->id,
            'scheduled_date' => '2026-11-14',
            'scheduled_time' => '10:00:00',
            'status' => 'pending',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-11-14 09:25:00', 'UTC'));

        $window = app(CleaningStartWindow::class);

        $this->assertSame(35, $window->minutesBefore($session));
        $this->assertSame(60, $window->minutesAfter($session));
        $this->assertFalse($window->isTooEarly($session));
        $this->assertTrue($window->canStart($session));
    }

    public function test_cleaner_is_blocked_until_the_property_window_opens(): void
    {
        $property = Property::factory()->create([
            'timezone' => 'UTC',
            'cleaner_start_window_minutes' => 20,
        ]);

        $session = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => User::factory()->create()->id,
            'housekeeper_id' => User::factory()->create()->id,
            'scheduled_date' => '2026-11-14',
            'scheduled_time' => '10:00:00',
            'status' => 'pending',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-11-14 09:39:00', 'UTC'));

        $window = app(CleaningStartWindow::class);

        $this->assertTrue($window->isTooEarly($session));
        $this->assertFalse($window->canStart($session));
    }

    public function test_after_allowance_is_enforced_independently(): void
    {
        $property = Property::factory()->create([
            'timezone' => 'UTC',
            'cleaner_start_window_minutes' => 120,
            'cleaner_end_window_minutes' => 60,
        ]);
        $session = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => User::factory()->create()->id,
            'housekeeper_id' => User::factory()->create()->id,
            'scheduled_date' => '2026-11-14',
            'scheduled_time' => '10:00:00',
            'status' => 'pending',
        ]);
        $window = app(CleaningStartWindow::class);
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:01:00', 'UTC'));
        $this->assertTrue($window->isTooLate($session));
        $this->assertFalse($window->canStart($session));
        $this->assertSame('2026-11-14T11:00:00+00:00', $window->closesAt($session)?->toIso8601String());
    }

    public function test_zero_allowances_require_exact_scheduled_time_when_window_is_enabled(): void
    {
        $property = Property::factory()->create([
            'timezone' => 'UTC',
            'cleaner_start_window_minutes' => 0,
            'cleaner_end_window_minutes' => 0,
            'cleaner_start_window_enabled' => true,
        ]);
        $session = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => User::factory()->create()->id,
            'housekeeper_id' => User::factory()->create()->id,
            'scheduled_date' => '2026-11-14',
            'scheduled_time' => '10:00:00',
            'status' => 'pending',
        ]);
        $window = app(CleaningStartWindow::class);
        Carbon::setTestNow(Carbon::parse('2026-11-14 09:59:00', 'UTC'));
        $this->assertTrue($window->isTooEarly($session));
        $this->assertFalse($window->canStart($session));
        Carbon::setTestNow(Carbon::parse('2026-11-14 10:00:00', 'UTC'));
        $this->assertFalse($window->isTooEarly($session));
        $this->assertFalse($window->isTooLate($session));
        $this->assertTrue($window->canStart($session));
        Carbon::setTestNow(Carbon::parse('2026-11-14 10:01:00', 'UTC'));
        $this->assertTrue($window->isTooLate($session));
        $this->assertFalse($window->canStart($session));
    }

    public function test_turning_the_window_off_uses_the_scheduled_time_instead_of_saved_allowances(): void
    {
        $property = Property::factory()->create([
            'timezone' => 'UTC',
            'cleaner_start_window_minutes' => 120,
            'cleaner_end_window_minutes' => 120,
            'cleaner_start_window_enabled' => false,
        ]);
        $session = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => User::factory()->create()->id,
            'housekeeper_id' => User::factory()->create()->id,
            'scheduled_date' => '2026-11-14',
            'scheduled_time' => '10:00:00',
            'status' => 'pending',
        ]);

        $window = app(CleaningStartWindow::class);

        Carbon::setTestNow(Carbon::parse('2026-11-14 09:59:00', 'UTC'));
        $this->assertSame('2026-11-14T10:00:00+00:00', $window->opensAt($session)?->toIso8601String());
        $this->assertTrue($window->isTooEarly($session));
        $this->assertFalse($window->isTooLate($session));
        $this->assertFalse($window->canStart($session));

        Carbon::setTestNow(Carbon::parse('2026-11-14 10:00:00', 'UTC'));
        $this->assertFalse($window->isTooEarly($session));
        $this->assertTrue($window->canStart($session));

        Carbon::setTestNow(Carbon::parse('2026-11-14 13:00:00', 'UTC'));
        $this->assertFalse($window->isTooLate($session));
        $this->assertTrue($window->canStart($session));
    }

}
