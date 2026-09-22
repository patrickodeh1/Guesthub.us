<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Room;
use App\Models\Task;
use App\Models\User;
use App\Models\CleaningSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructionFamiliarityTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $housekeeper;
    protected Property $property;
    protected Room $room;
    protected CleaningSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'housekeeper', 'guard_name' => 'web']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->housekeeper = User::factory()->create([
            'preferences' => ['required_instruction_views' => 3],
        ]);
        $this->housekeeper->assignRole('housekeeper');

        $this->property = Property::factory()->create([
            'owner_id' => $this->owner->id,
        ]);

        $this->room = Room::factory()->create();
        DB::table('property_room')->insert([
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'sort_order' => 1,
        ]);

        $this->session = CleaningSession::factory()->create([
            'property_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'housekeeper_id' => $this->housekeeper->id,
            'status' => 'in_progress',
        ]);

        Cache::flush();
    }

    public function test_instruction_views_increment_cumulatively()
    {
        $task = Task::factory()->create([
            'name' => 'Test Task',
            'type' => 'room',
            'instructions' => 'Instruction text',
        ]);

        DB::table('room_task')->insert([
            'room_id' => $this->room->id,
            'task_id' => $task->id,
            'sort_order' => 1,
        ]);

        // 1st View
        $response1 = $this->actingAs($this->housekeeper)
            ->postJson(route('checklist.view-instruction', [
                'session' => $this->session->id,
                'room' => $this->room->id,
                'task' => $task->id,
            ]));
            
        $response1->assertOk()
            ->assertJson([
                'success' => true,
                'views_completed' => 1,
                // Familiarity enforcement is disabled: required_views comes from user
                // preferences but is_familiar is always true since the backend now
                // sets $requiredViews = 0 for session data. The view-instruction
                // endpoint still uses the user preference for its own response.
            ]);

        $this->assertDatabaseHas('cleaner_instruction_familiarities', [
            'user_id' => $this->housekeeper->id,
            'task_id' => $task->id,
            'views_completed' => 1,
        ]);

        // 2nd View
        $response2 = $this->actingAs($this->housekeeper)
            ->postJson(route('checklist.view-instruction', [
                'session' => $this->session->id,
                'room' => $this->room->id,
                'task' => $task->id,
            ]));
            
        $response2->assertOk()
            ->assertJson([
                'success' => true,
                'views_completed' => 2,
            ]);

        $this->assertDatabaseHas('cleaner_instruction_familiarities', [
            'user_id' => $this->housekeeper->id,
            'task_id' => $task->id,
            'views_completed' => 2,
        ]);

        // 3rd View
        $response3 = $this->actingAs($this->housekeeper)
            ->postJson(route('checklist.view-instruction', [
                'session' => $this->session->id,
                'room' => $this->room->id,
                'task' => $task->id,
            ]));
            
        $response3->assertOk()
            ->assertJson([
                'success' => true,
                'views_completed' => 3,
            ]);

        $this->assertDatabaseHas('cleaner_instruction_familiarities', [
            'user_id' => $this->housekeeper->id,
            'task_id' => $task->id,
            'views_completed' => 3,
        ]);
        
        // Admin Reset
        \Illuminate\Support\Facades\DB::table('cleaner_instruction_familiarities')
            ->where('user_id', $this->housekeeper->id)
            ->where('task_id', $task->id)
            ->update(['views_completed' => 0]);
        
        // 4th View (after reset)
        $response4 = $this->actingAs($this->housekeeper)
            ->postJson(route('checklist.view-instruction', [
                'session' => $this->session->id,
                'room' => $this->room->id,
                'task' => $task->id,
            ]));
            
        $response4->assertOk()
            ->assertJson([
                'success' => true,
                'views_completed' => 1,
            ]);
    }
    
    public function test_property_task_views_increment_cumulatively()
    {
        $task = Task::factory()->create([
            'name' => 'Property Task',
            'type' => 'room',
            'instructions' => 'Instruction text',
        ]);

        DB::table('property_tasks')->insert([
            'property_id' => $this->property->id,
            'task_id' => $task->id,
            'sort_order' => 1,
        ]);

        // 1st View
        $response1 = $this->actingAs($this->housekeeper)
            ->postJson(route('checklist.property-task.view-instruction', [
                'session' => $this->session->id,
                'task' => $task->id,
            ]));
            
        $response1->assertOk()
            ->assertJson([
                'success' => true,
                'views_completed' => 1,
            ]);

        $this->assertDatabaseHas('cleaner_instruction_familiarities', [
            'user_id' => $this->housekeeper->id,
            'task_id' => $task->id,
            'views_completed' => 1,
        ]);
        
        // 2nd View
        $response2 = $this->actingAs($this->housekeeper)
            ->postJson(route('checklist.property-task.view-instruction', [
                'session' => $this->session->id,
                'task' => $task->id,
            ]));
            
        $response2->assertOk()
            ->assertJson([
                'success' => true,
                'views_completed' => 2,
            ]);
    }
}
