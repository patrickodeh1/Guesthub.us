<?php

namespace Tests\Feature;

use App\Models\CleaningSession;
use App\Models\Property;
use App\Models\Task;
use App\Models\User;
use App\Services\CleanerAccessSteps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanerLockboxOptionalTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_unselected_sporadic_lockbox_task_does_not_block_the_exit_wizard(): void
    {
        [, , $cleaner, , $session] = $this->sessionWithOptionalLockbox();

        $this->actingAs($cleaner);

        $this->assertSame([], CleanerAccessSteps::exitForSession($session));
    }

    public function test_selected_sporadic_lockbox_task_is_kept_in_the_exit_wizard(): void
    {
        [, , $cleaner, $task, $session] = $this->sessionWithOptionalLockbox(true);

        $this->actingAs($cleaner);

        $steps = CleanerAccessSteps::exitForSession($session);

        $this->assertCount(1, $steps);
        $this->assertSame($task->name, $steps[0]['title']);
        $this->assertNotEmpty($steps[0]['lockbox_url']);
    }

    private function sessionWithOptionalLockbox(bool $selected = false): array
    {
        $owner = User::factory()->create();
        $cleaner = User::factory()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $task = Task::factory()->create([
            'created_by' => $owner->id,
            'type' => 'lockbox',
            'phase' => 'during_cleaning',
            'is_sporadic' => true,
        ]);
        $property->propertyTasks()->attach($task->id, [
            'sort_order' => 1,
            'instructions' => null,
            'visible_to_owner' => true,
            'visible_to_housekeeper' => true,
        ]);
        $session = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'housekeeper_id' => $cleaner->id,
            'status' => 'in_progress',
            'sporadic_tasks' => $selected ? [$task->id . '_global'] : [],
        ]);

        return [$property, $owner, $cleaner, $task, $session];
    }
}
