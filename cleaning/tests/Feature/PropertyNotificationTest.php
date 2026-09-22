<?php

namespace Tests\Feature;

use App\Models\CleaningSession;
use App\Models\NotificationLog;
use App\Models\Property;
use App\Models\PropertyNotificationRecipient;
use App\Models\Task;
use App\Models\User;
use App\Services\SmsNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PropertyNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create role if it doesn't exist (since RefreshDatabase clears it)
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->property = Property::factory()->create([
            'notify_cleaning_started' => true,
            'notify_cleaning_finished' => true,
            'notify_photo_started' => true,
            'notify_task_notes' => true,
        ]);

        PropertyNotificationRecipient::create([
            'property_id' => $this->property->id,
            'phone_number' => '+1234567890',
            'recipient_name' => 'Test Recipient',
            'active' => true,
        ]);
    }

    public function test_can_update_notification_settings()
    {
        $response = $this->actingAs($this->admin)->putJson(route('properties.notifications.update-settings', $this->property), [
            'notify_cleaning_started' => false,
            'notify_cleaning_finished' => true,
            'notify_photo_started' => false,
            'notify_task_notes' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->property->refresh();
        $this->assertFalse($this->property->notify_cleaning_started);
        $this->assertTrue($this->property->notify_cleaning_finished);
    }

    public function test_can_add_and_remove_recipients()
    {
        // Add
        $response = $this->actingAs($this->admin)->postJson(route('properties.notifications.add-recipient', $this->property), [
            'phone_number' => '+0987654321',
            'recipient_name' => 'New Guy',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('property_notification_recipients', [
            'phone_number' => '+0987654321',
            'recipient_name' => 'New Guy',
        ]);

        $recipientId = $response->json('recipient.id');

        // Delete
        $deleteResponse = $this->actingAs($this->admin)->deleteJson(route('properties.notifications.delete-recipient', [$this->property, $recipientId]));
        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('property_notification_recipients', [
            'id' => $recipientId,
        ]);
    }

    public function test_sms_service_logs_cleaning_started()
    {
        $session = CleaningSession::factory()->create([
            'property_id' => $this->property->id,
        ]);

        // Spy on Log
        Log::shouldReceive('channel')->with('stack')->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(function($msg) {
            return str_contains($msg, 'Cleaning has started');
        });

        SmsNotificationService::sendCleaningStarted($session);

        $this->assertDatabaseHas('notification_logs', [
            'property_id' => $this->property->id,
            'cleaning_session_id' => $session->id,
            'notification_type' => 'started',
            'delivery_status' => 'sent',
        ]);
    }

    public function test_idempotency_prevents_duplicate_started_sms()
    {
        $session = CleaningSession::factory()->create([
            'property_id' => $this->property->id,
        ]);

        // Send first time
        SmsNotificationService::sendCleaningStarted($session);
        $this->assertEquals(1, NotificationLog::where('notification_type', 'started')->count());

        // Send second time
        SmsNotificationService::sendCleaningStarted($session);
        // Count should still be 1
        $this->assertEquals(1, NotificationLog::where('notification_type', 'started')->count());
    }

    public function test_task_notes_are_not_idempotent()
    {
        $session = CleaningSession::factory()->create([
            'property_id' => $this->property->id,
        ]);
        $task = Task::factory()->create();

        SmsNotificationService::sendTaskNote($session, $task, 'Note 1');
        SmsNotificationService::sendTaskNote($session, $task, 'Note 2');

        $this->assertEquals(2, NotificationLog::where('notification_type', 'note')->count());
    }
}
