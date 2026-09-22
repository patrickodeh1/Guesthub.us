<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Property;
use App\Models\Room;
use App\Models\Task;
use App\Models\CleaningSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SessionInstructionsTest extends TestCase
{
    use RefreshDatabase;

    protected $owner;
    protected $housekeeper;
    protected $admin;
    protected $otherUser;
    protected $property;
    protected $room;
    protected $task;
    protected $propertyTask;
    protected $session;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'housekeeper', 'guard_name' => 'web']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->housekeeper = User::factory()->create();
        $this->housekeeper->assignRole('housekeeper');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->otherUser = User::factory()->create();
        $this->otherUser->assignRole('housekeeper');

        $this->property = Property::factory()->create([
            'owner_id' => $this->owner->id,
        ]);

        $this->room = Room::factory()->create();
        DB::table('property_room')->insert([
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'sort_order' => 1,
        ]);

        $this->task = Task::factory()->create([
            'name' => 'Test Room Task',
            'type' => 'room',
            'instructions' => 'Global Room Task Instructions',
        ]);
        DB::table('room_task')->insert([
            'room_id' => $this->room->id,
            'task_id' => $this->task->id,
            'instructions' => 'Custom Room Task Pivot Instructions',
            'sort_order' => 1,
        ]);

        $this->propertyTask = Task::factory()->create([
            'name' => 'Test Property Task',
            'type' => 'room',
            'instructions' => 'Global Property Task Instructions',
        ]);
        DB::table('property_tasks')->insert([
            'property_id' => $this->property->id,
            'task_id' => $this->propertyTask->id,
            'instructions' => 'Custom Property Task Pivot Instructions',
            'sort_order' => 1,
        ]);

        $this->session = CleaningSession::factory()->create([
            'property_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'housekeeper_id' => $this->housekeeper->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_guest_cannot_access_instructions(): void
    {
        $response = $this->getJson(route('sessions.instructions', [
            'session' => $this->session->id,
            'room_id' => $this->room->id,
            'task_id' => $this->task->id,
        ]));

        $response->assertUnauthorized();
    }

    public function test_unauthorized_housekeeper_cannot_access_instructions(): void
    {
        $response = $this->actingAs($this->otherUser)
            ->getJson(route('sessions.instructions', [
                'session' => $this->session->id,
                'room_id' => $this->room->id,
                'task_id' => $this->task->id,
            ]));

        $response->assertForbidden();
    }

    public function test_assigned_housekeeper_can_access_instructions(): void
    {
        $response = $this->actingAs($this->housekeeper)
            ->getJson(route('sessions.instructions', [
                'session' => $this->session->id,
                'room_id' => $this->room->id,
                'task_id' => $this->task->id,
            ]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'task' => [
                        'id',
                        'name',
                        'type',
                        'instructions',
                        'media',
                    ],
                    'room_instructions',
                    'property_instructions',
                ],
            ]);
    }

    public function test_admin_and_owner_can_access_instructions(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('sessions.instructions', $this->session->id))
            ->assertOk();

        $this->actingAs($this->owner)
            ->getJson(route('sessions.instructions', $this->session->id))
            ->assertOk();
    }

    public function test_validation_for_unrelated_room(): void
    {
        $unrelatedRoom = Room::factory()->create();

        $response = $this->actingAs($this->housekeeper)
            ->getJson(route('sessions.instructions', [
                'session' => $this->session->id,
                'room_id' => $unrelatedRoom->id,
            ]));

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Room is not attached to this property.',
            ]);
    }

    public function test_validation_for_unrelated_task_in_room(): void
    {
        $unrelatedTask = Task::factory()->create();

        $response = $this->actingAs($this->housekeeper)
            ->getJson(route('sessions.instructions', [
                'session' => $this->session->id,
                'room_id' => $this->room->id,
                'task_id' => $unrelatedTask->id,
            ]));

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Task is not attached to this room.',
            ]);
    }

    public function test_validation_for_unrelated_property_task(): void
    {
        $unrelatedTask = Task::factory()->create();

        $response = $this->actingAs($this->housekeeper)
            ->getJson(route('sessions.instructions', [
                'session' => $this->session->id,
                'task_id' => $unrelatedTask->id,
            ]));

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Task is not attached to this property.',
            ]);
    }

    public function test_custom_instructions_structure_and_values(): void
    {
        // Add an instructions task to test general room instructions
        $roomInstructionTask = Task::factory()->create([
            'name' => 'General Room Guideline Task',
            'type' => 'instructions',
            'instructions' => 'Do not use bleach in bathroom',
        ]);
        DB::table('room_task')->insert([
            'room_id' => $this->room->id,
            'task_id' => $roomInstructionTask->id,
            'instructions' => 'Override: Do not use bleach in bathroom!',
            'sort_order' => 2,
        ]);

        // Add an instructions task to test property guidelines
        $propertyInstructionTask = Task::factory()->create([
            'name' => 'General Property Guideline Task',
            'type' => 'instructions',
            'instructions' => 'Lock back door when leaving',
        ]);
        DB::table('property_tasks')->insert([
            'property_id' => $this->property->id,
            'task_id' => $propertyInstructionTask->id,
            'instructions' => 'Override: Lock back door when leaving!',
            'sort_order' => 2,
        ]);

        $response = $this->actingAs($this->housekeeper)
            ->getJson(route('sessions.instructions', [
                'session' => $this->session->id,
                'room_id' => $this->room->id,
                'task_id' => $this->task->id,
            ]));

        $response->assertOk();
        
        $data = $response->json('data');

        // Check active task custom instructions
        $this->assertEquals($this->task->id, $data['task']['id']);
        $this->assertEquals('Custom Room Task Pivot Instructions', $data['task']['instructions']);

        // Check general room instructions
        $this->assertCount(1, $data['room_instructions']);
        $this->assertEquals('General Room Guideline Task', $data['room_instructions'][0]['task_name']);
        $this->assertEquals('Override: Do not use bleach in bathroom!', $data['room_instructions'][0]['instructions']);

        // Check property guidelines
        $this->assertCount(1, $data['property_instructions']);
        $this->assertEquals('General Property Guideline Task', $data['property_instructions'][0]['task_name']);
        $this->assertEquals('Override: Lock back door when leaving!', $data['property_instructions'][0]['instructions']);
    }

    public function test_caching_and_invalidation(): void
    {
        Cache::flush();

        $cacheKey = "session_instructions_{$this->session->id}_{$this->room->id}_{$this->task->id}";
        $this->assertFalse(Cache::has($cacheKey));

        // First call populates cache
        $this->actingAs($this->housekeeper)
            ->getJson(route('sessions.instructions', [
                'session' => $this->session->id,
                'room_id' => $this->room->id,
                'task_id' => $this->task->id,
            ]))
            ->assertOk();

        $this->assertTrue(Cache::has($cacheKey));

        // Update task should trigger Cache::flush() via Task model event listeners
        $this->task->update(['instructions' => 'Updated Instructions']);

        $this->assertFalse(Cache::has($cacheKey));
    }
}
