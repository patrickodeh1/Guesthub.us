<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ReportItemFilter;
use App\Models\CleaningSession;
use App\Models\ChecklistItem;
use App\Models\Task;
use App\Models\Property;
use App\Models\User;
use App\Models\ChecklistItemPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

class ReportItemFilterTest extends TestCase
{
    use RefreshDatabase;

    protected ReportItemFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new ReportItemFilter();
    }

    private function createSession()
    {
        $owner = User::factory()->create();
        $housekeeper = User::factory()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        return CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'housekeeper_id' => $housekeeper->id,
        ]);
    }

    public function test_get_issues_returns_only_unchecked_items_with_images()
    {
        $session = $this->createSession();
        $task = Task::factory()->create();
        $room = \App\Models\Room::factory()->create();
        $defaults = ['session_id' => $session->id, 'task_id' => $task->id, 'room_id' => $room->id, 'user_id' => $session->housekeeper_id];

        // 1. Unchecked + no image -> Should NOT be in results
        $item1 = ChecklistItem::factory()->create(array_merge($defaults, ['checked' => false]));
        
        // 2. Unchecked + has image -> Should be IN results
        $item2 = ChecklistItem::factory()->create(array_merge($defaults, ['checked' => false]));
        ChecklistItemPhoto::forceCreate(['checklist_item_id' => $item2->id, 'path' => 'test.jpg']);
        
        // 3. Checked + has image -> Should NOT be in results
        $item3 = ChecklistItem::factory()->create(array_merge($defaults, ['checked' => true]));
        ChecklistItemPhoto::forceCreate(['checklist_item_id' => $item3->id, 'path' => 'test2.jpg']);

        // Reload session to get relations
        $session->load('checklistItems.photos', 'checklistItems.task');

        $issues = $this->filter->getIssues($session);

        $this->assertCount(1, $issues);
        $this->assertTrue($issues->contains('id', $item2->id));
        $this->assertFalse($issues->contains('id', $item1->id));
        $this->assertFalse($issues->contains('id', $item3->id));
    }

    public function test_get_compliance_items_returns_only_verify_status_items()
    {
        $session = $this->createSession();
        $room = \App\Models\Room::factory()->create();

        $verifyTask = Task::factory()->create(['type' => 'verify']);
        $normalTask = Task::factory()->create(['type' => 'room']);

        $defaultsVerify = ['session_id' => $session->id, 'task_id' => $verifyTask->id, 'room_id' => $room->id, 'user_id' => $session->housekeeper_id];
        $defaultsNormal = ['session_id' => $session->id, 'task_id' => $normalTask->id, 'room_id' => $room->id, 'user_id' => $session->housekeeper_id];

        // 1. Verify status + checked -> Should be IN results
        $item1 = ChecklistItem::factory()->create(array_merge($defaultsVerify, ['checked' => true]));
        
        // 2. Verify status + unchecked -> Should be IN results
        $item2 = ChecklistItem::factory()->create(array_merge($defaultsVerify, ['checked' => false]));
        
        // 3. Not verify status + checked -> Should NOT be in results
        $item3 = ChecklistItem::factory()->create(array_merge($defaultsNormal, ['checked' => true]));

        // Reload session
        $session->load('checklistItems.photos', 'checklistItems.task');

        $compliance = $this->filter->getComplianceItems($session);

        $this->assertCount(2, $compliance);
        $this->assertTrue($compliance->contains('id', $item1->id));
        $this->assertTrue($compliance->contains('id', $item2->id));
        $this->assertFalse($compliance->contains('id', $item3->id));
    }
}
