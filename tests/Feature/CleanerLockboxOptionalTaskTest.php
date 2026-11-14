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
        [$property, $owner, $cleaner, $task, $session] = $this->sessionWithOptionalLockbox();

        $this->actingAs($cleaner);

        $steps = CleanerAccessSteps::exitForSession($session);

        $this->assertSame([], $steps);
    }

    public function test_selected_sporadic_lockbox_task_is_kept_in_the_exit_wizard(): void
    {
        [$property, $owner, $cleaner, $task, $session] = $this->sessionWithOptionalLockbox([
            $taskKey = null,
        ]);
    }

    private function sessionWithOptionalLockbox(array $selected = []): array
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
            'sporadic_tasks' => $selected,
        ]);

        return [$property, $owner, $cleaner, $task, $session];
    }
}
