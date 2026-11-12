<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CleaningSelfAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_managers_and_admins_can_self_assign_without_sms_confirmation(): void
    {
        foreach (['owner', 'manager', 'admin'] as $index => $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

            $owner = User::factory()->create(['email_verified_at' => now()]);
            $actor = $roleName === 'owner' ? $owner : User::factory()->create(['email_verified_at' => now()]);
            $actor->assignRole($roleName);

            $property = Property::factory()->create(['owner_id' => $owner->id]);
            if ($roleName === 'manager') {
                $actor->properties()->attach($property->id);
            }

            $task = Task::factory()->create(['created_by' => $owner->id]);
            $property->propertyTasks()->attach($task->id, [
                'sort_order' => 1,
                'instructions' => null,
                'visible_to_owner' => true,
                'visible_to_housekeeper' => true,
            ]);

            $date = now()->addDays(5 + $index)->toDateString();
            $options = $this->actingAs($actor)->get(route('admin.cleaning-jobs.assign-options', [
                'property_id' => $property->id,
            ]));

            $options->assertOk()
                ->assertJsonFragment([
                    'id' => $actor->id,
                    'name' => $actor->name,
                    'has_phone' => true,
                    'is_self' => true,
                ]);

            $this->postJson(route('admin.cleaning-jobs.calendar-assign'), [
                'property_id' => $property->id,
                'scheduled_date' => $date,
                'housekeeper_id' => $actor->id,
            ])->assertOk()->assertJson(['ok' => true]);

            $this->assertDatabaseHas('cleaning_sessions', [
                'property_id' => $property->id,
                'housekeeper_id' => $actor->id,
                'assignment_status' => 'confirmed',
                'cleaner_notified_at' => null,
            ]);
            $this->assertSame(
                $date,
                \App\Models\CleaningSession::query()
                    ->where('property_id', $property->id)
                    ->where('housekeeper_id', $actor->id)
                    ->firstOrFail()
                    ->scheduled_date
                    ->toDateString()
            );
        }
    }
}
