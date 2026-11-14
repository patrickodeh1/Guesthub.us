<?php

namespace Tests\Feature;

use App\Models\CleaningSession;
use App\Models\Property;
use App\Models\User;
use App\Services\CleaningStartWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleaningStartWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
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
            'scheduled_time' => '2026-11-14 10:00:00',
            'status' => 'pending',
        ]);

        \Carbon\Carbon::setTestNow('2026-11-14 09:25:00');

        $window = app(CleaningStartWindow::class);

        $this->assertSame(35, $window->minutesBefore($session));
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
            'scheduled_time' => '2026-11-14 10:00:00',
            'status' => 'pending',
        ]);

        \Carbon\Carbon::setTestNow('2026-11-14 09:39:00');

        $window = app(CleaningStartWindow::class);

        $this->assertTrue($window->isTooEarly($session));
        $this->assertFalse($window->canStart($session));
    }
}
