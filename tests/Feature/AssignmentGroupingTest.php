<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Property;
use App\Models\CleaningSession;
use App\Services\AssignmentGroupingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssignmentGroupingTest extends TestCase
{
    use RefreshDatabase;

    protected $housekeeper;
    protected $property;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist
        Role::firstOrCreate(['name' => 'housekeeper', 'guard_name' => 'web']);

        $this->housekeeper = User::factory()->create();
        $this->housekeeper->assignRole('housekeeper');

        $this->property = Property::factory()->create();
    }

    // ── Service Unit Tests ──────────────────────────────────────

    public function test_get_date_label_returns_correct_labels(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-17 12:00:00'));

        $this->assertEquals('Today', AssignmentGroupingService::getDateLabel(Carbon::parse('2026-06-17')));
        $this->assertEquals('Tomorrow', AssignmentGroupingService::getDateLabel(Carbon::parse('2026-06-18')));
        $this->assertEquals('Yesterday', AssignmentGroupingService::getDateLabel(Carbon::parse('2026-06-16')));
        
        $this->assertEquals('In 2 days', AssignmentGroupingService::getDateLabel(Carbon::parse('2026-06-19')));
        $this->assertEquals('In 3 days', AssignmentGroupingService::getDateLabel(Carbon::parse('2026-06-20')));
        
        $this->assertEquals('2 days ago', AssignmentGroupingService::getDateLabel(Carbon::parse('2026-06-15')));
        $this->assertEquals('3 days ago', AssignmentGroupingService::getDateLabel(Carbon::parse('2026-06-14')));
        
        $this->assertEquals('Wednesday, June 24, 2026', AssignmentGroupingService::getDateLabel(Carbon::parse('2026-06-24')));
        $this->assertEquals('Wednesday, June 10, 2026', AssignmentGroupingService::getDateLabel(Carbon::parse('2026-06-10')));

        Carbon::setTestNow(); // Reset Carbon time mock
    }

    public function test_get_date_sort_key_priority(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-17 12:00:00'));

        $this->assertEquals(0, AssignmentGroupingService::getDateSortKey('Today'));
        $this->assertEquals(1, AssignmentGroupingService::getDateSortKey('Tomorrow'));
        $this->assertEquals(2, AssignmentGroupingService::getDateSortKey('In 2 days'));
        $this->assertEquals(100001, AssignmentGroupingService::getDateSortKey('Yesterday'));
        $this->assertEquals(100002, AssignmentGroupingService::getDateSortKey('2 days ago'));
        $this->assertEquals(999999, AssignmentGroupingService::getDateSortKey('No Date Assigned'));

        Carbon::setTestNow();
    }

    public function test_group_assignments_by_date_groups_and_sorts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-17 12:00:00'));

        $s1 = CleaningSession::factory()->create([
            'housekeeper_id' => $this->housekeeper->id,
            'property_id' => $this->property->id,
            'scheduled_date' => '2026-06-17', // Today
        ]);

        $s2 = CleaningSession::factory()->create([
            'housekeeper_id' => $this->housekeeper->id,
            'property_id' => $this->property->id,
            'scheduled_date' => '2026-06-18', // Tomorrow
        ]);

        $s3 = CleaningSession::factory()->create([
            'housekeeper_id' => $this->housekeeper->id,
            'property_id' => $this->property->id,
            'scheduled_date' => '2026-06-16', // Yesterday
        ]);

        // Create an in-memory session (not saved in DB) to test the null date label grouping
        $s4 = new CleaningSession([
            'housekeeper_id' => $this->housekeeper->id,
            'property_id' => $this->property->id,
            'scheduled_date' => null, // Unscheduled
        ]);

        // Test Default Descending Order (Today/Past view, tomorrow is in the list here just to test mixing)
        $grouped = AssignmentGroupingService::groupAssignmentsByDate([$s1, $s2, $s3, $s4], false);
        $labels = array_keys($grouped);
        $this->assertEquals(['Tomorrow', 'Today', 'Yesterday', 'No Date Assigned'], $labels);

        $this->assertEquals($s2->id, $grouped['Tomorrow'][0]->id);
        $this->assertEquals($s1->id, $grouped['Today'][0]->id);
        $this->assertEquals($s3->id, $grouped['Yesterday'][0]->id);
        $this->assertNull($grouped['No Date Assigned'][0]->id);

        // Test Upcoming Ascending Order (Future view, past dates in list just to test sorting priority)
        $groupedUpcoming = AssignmentGroupingService::groupAssignmentsByDate([$s1, $s2, $s3, $s4], true);
        $labelsUpcoming = array_keys($groupedUpcoming);
        $this->assertEquals(['Yesterday', 'Today', 'Tomorrow', 'No Date Assigned'], $labelsUpcoming);

        Carbon::setTestNow();
    }

    // ── Controller Integration Tests ────────────────────────────

    public function test_housekeeper_can_view_grouped_assignments(): void
    {
        $response = $this->actingAs($this->housekeeper)
            ->get(route('assignments.index'));

        $response->assertOk();
    }

    public function test_guest_cannot_view_assignments(): void
    {
        $response = $this->get(route('assignments.index'));
        $response->assertRedirect('/login');
    }

    public function test_assignments_filters(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-17 12:00:00'));

        // Today
        $s1 = CleaningSession::factory()->create([
            'housekeeper_id' => $this->housekeeper->id,
            'property_id' => $this->property->id,
            'scheduled_date' => '2026-06-17',
            'status' => 'pending',
        ]);

        // Future
        $s2 = CleaningSession::factory()->create([
            'housekeeper_id' => $this->housekeeper->id,
            'property_id' => $this->property->id,
            'scheduled_date' => '2026-06-19',
            'status' => 'pending',
        ]);

        // Overdue (Yesterday pending)
        $s3 = CleaningSession::factory()->create([
            'housekeeper_id' => $this->housekeeper->id,
            'property_id' => $this->property->id,
            'scheduled_date' => '2026-06-16',
            'status' => 'pending',
        ]);

        // Today completed
        $s4 = CleaningSession::factory()->create([
            'housekeeper_id' => $this->housekeeper->id,
            'property_id' => $this->property->id,
            'scheduled_date' => '2026-06-17',
            'status' => 'completed',
        ]);

        // Test Today Filter
        $response = $this->actingAs($this->housekeeper)
            ->get(route('assignments.index', ['filter' => 'today']));
        $response->assertOk();
        $response->assertSee('<span>Today</span>', false);
        $response->assertDontSee('<span>In 2 days</span>', false);

        // Test Upcoming Filter (Today + Future)
        $response = $this->actingAs($this->housekeeper)
            ->get(route('assignments.index', ['filter' => 'upcoming']));
        $response->assertOk();
        $response->assertSee('<span>Today</span>', false);
        $response->assertSee('<span>In 2 days</span>', false);
        $response->assertDontSee('<span>Yesterday</span>', false);

        // Test Overdue Filter (Yesterday pending)
        $response = $this->actingAs($this->housekeeper)
            ->get(route('assignments.index', ['filter' => 'overdue']));
        $response->assertOk();
        $response->assertSee('<span>Yesterday</span>', false);
        // Assert that the 'Today' group header is not visible (prevents matching 'Today Only' tab)
        $response->assertDontSee('<span>Today</span>', false);

        Carbon::setTestNow();
    }
}
