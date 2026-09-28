<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Property;
use App\Models\CleaningSession;
use App\Models\InstructionalVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructionalVideoTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $owner;
    protected $otherOwner;
    protected $housekeeper;
    protected $property;
    protected $otherProperty;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'company', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'housekeeper', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->otherOwner = User::factory()->create();
        $this->otherOwner->assignRole('owner');

        $this->housekeeper = User::factory()->create();
        $this->housekeeper->assignRole('housekeeper');

        $this->property = Property::factory()->create([
            'owner_id' => $this->owner->id,
        ]);

        $this->otherProperty = Property::factory()->create([
            'owner_id' => $this->otherOwner->id,
        ]);

        Storage::fake('public');
    }

    // ── Admin Video Management ──────────────────────────────────

    public function test_admin_can_view_video_library_index(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.videos.index'));

        $response->assertOk();
    }

    public function test_owner_can_view_video_library_index(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('admin.videos.index'));

        $response->assertOk();
    }

    public function test_housekeeper_cannot_view_video_library_index(): void
    {
        $response = $this->actingAs($this->housekeeper)
            ->get(route('admin.videos.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_video(): void
    {
        $video = UploadedFile::fake()->create('training.mp4', 5000, 'video/mp4');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.videos.store'), [
                'title' => 'Test Training Video',
                'description' => 'A test description.',
                'category' => 'general',
                'video' => $video,
                'properties' => [$this->property->id],
            ]);

        $response->assertRedirect(route('admin.videos.index'));

        $this->assertDatabaseHas('instructional_videos', [
            'title' => 'Test Training Video',
            'category' => 'general',
            'created_by' => $this->admin->id,
        ]);

        // Verify pivot record
        $createdVideo = InstructionalVideo::where('title', 'Test Training Video')->first();
        $this->assertNotNull($createdVideo);
        $this->assertTrue($createdVideo->properties->contains($this->property->id));
    }

    public function test_owner_can_create_video(): void
    {
        $video = UploadedFile::fake()->create('owner-training.mp4', 5000, 'video/mp4');

        $response = $this->actingAs($this->owner)
            ->post(route('admin.videos.store'), [
                'title' => 'Owner Training Video',
                'category' => 'safety',
                'video' => $video,
                'properties' => [$this->property->id],
            ]);

        $response->assertRedirect(route('admin.videos.index'));

        $this->assertDatabaseHas('instructional_videos', [
            'title' => 'Owner Training Video',
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_housekeeper_cannot_create_video(): void
    {
        $video = UploadedFile::fake()->create('hk-training.mp4', 5000, 'video/mp4');

        $response = $this->actingAs($this->housekeeper)
            ->post(route('admin.videos.store'), [
                'title' => 'HK Training Video',
                'category' => 'general',
                'video' => $video,
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('instructional_videos', ['title' => 'HK Training Video']);
    }

    public function test_owner_cannot_edit_another_owners_video(): void
    {
        $otherVideo = InstructionalVideo::create([
            'title' => 'Other Owner Video',
            'video_file_path' => 'videos/other.mp4',
            'category' => 'equipment',
            'created_by' => $this->otherOwner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('admin.videos.edit', $otherVideo));

        $response->assertForbidden();
    }

    public function test_admin_can_edit_any_video(): void
    {
        $ownerVideo = InstructionalVideo::create([
            'title' => 'Owner Created Video',
            'video_file_path' => 'videos/owner.mp4',
            'category' => 'process',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.videos.edit', $ownerVideo));

        $response->assertOk();
    }

    public function test_owner_cannot_delete_another_owners_video(): void
    {
        $otherVideo = InstructionalVideo::create([
            'title' => 'Other Video To Delete',
            'video_file_path' => 'videos/other-del.mp4',
            'category' => 'safety',
            'created_by' => $this->otherOwner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->delete(route('admin.videos.destroy', $otherVideo));

        $response->assertForbidden();
        $this->assertDatabaseHas('instructional_videos', ['id' => $otherVideo->id]);
    }

    public function test_admin_can_delete_any_video(): void
    {
        $ownerVideo = InstructionalVideo::create([
            'title' => 'Video For Admin Delete',
            'video_file_path' => 'videos/admin-del.mp4',
            'category' => 'general',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.videos.destroy', $ownerVideo));

        $response->assertRedirect(route('admin.videos.index'));
        $this->assertDatabaseMissing('instructional_videos', ['id' => $ownerVideo->id]);
    }

    public function test_toggle_publish_status(): void
    {
        $video = InstructionalVideo::create([
            'title' => 'Toggle Publish Test',
            'video_file_path' => 'videos/toggle.mp4',
            'category' => 'general',
            'is_published' => false,
            'created_by' => $this->owner->id,
        ]);

        // Toggle to published
        $response = $this->actingAs($this->owner)
            ->post(route('admin.videos.publish', $video));

        $response->assertRedirect();
        $video->refresh();
        $this->assertTrue($video->is_published);

        // Toggle back to draft
        $response = $this->actingAs($this->owner)
            ->post(route('admin.videos.publish', $video));

        $response->assertRedirect();
        $video->refresh();
        $this->assertFalse($video->is_published);
    }

    // ── Cleaner API Endpoint ────────────────────────────────────

    public function test_cleaner_can_fetch_published_videos_for_assigned_property(): void
    {
        // Create a session assigning the housekeeper to the property
        CleaningSession::factory()->create([
            'property_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'housekeeper_id' => $this->housekeeper->id,
            'status' => 'in_progress',
        ]);

        // Create published video assigned to the property
        $publishedVideo = InstructionalVideo::create([
            'title' => 'Published Cleaner Video',
            'video_file_path' => 'videos/pub.mp4',
            'category' => 'general',
            'is_published' => true,
            'created_by' => $this->owner->id,
        ]);
        $publishedVideo->properties()->attach($this->property->id);

        // Create draft video (should NOT appear)
        $draftVideo = InstructionalVideo::create([
            'title' => 'Draft Video',
            'video_file_path' => 'videos/draft.mp4',
            'category' => 'safety',
            'is_published' => false,
            'created_by' => $this->owner->id,
        ]);
        $draftVideo->properties()->attach($this->property->id);

        $response = $this->actingAs($this->housekeeper)
            ->getJson(route('api.property-videos', ['property_id' => $this->property->id]));

        $response->assertOk();

        $data = $response->json();
        $titles = array_column($data, 'title');
        $this->assertContains('Published Cleaner Video', $titles);
        $this->assertNotContains('Draft Video', $titles);
    }

    public function test_cleaner_cannot_fetch_videos_for_unassigned_property(): void
    {
        $response = $this->actingAs($this->housekeeper)
            ->getJson(route('api.property-videos', ['property_id' => $this->otherProperty->id]));

        $response->assertStatus(403);
    }

    public function test_api_filters_by_category(): void
    {
        CleaningSession::factory()->create([
            'property_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'housekeeper_id' => $this->housekeeper->id,
            'status' => 'in_progress',
        ]);

        $generalVideo = InstructionalVideo::create([
            'title' => 'General Video',
            'video_file_path' => 'videos/gen.mp4',
            'category' => 'general',
            'is_published' => true,
            'created_by' => $this->owner->id,
        ]);
        $generalVideo->properties()->attach($this->property->id);

        $safetyVideo = InstructionalVideo::create([
            'title' => 'Safety Video',
            'video_file_path' => 'videos/safe.mp4',
            'category' => 'safety',
            'is_published' => true,
            'created_by' => $this->owner->id,
        ]);
        $safetyVideo->properties()->attach($this->property->id);

        $response = $this->actingAs($this->housekeeper)
            ->getJson(route('api.property-videos', [
                'property_id' => $this->property->id,
                'category' => 'safety',
            ]));

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals('Safety Video', $data[0]['title']);
    }

    public function test_guest_cannot_access_api(): void
    {
        $response = $this->getJson(route('api.property-videos', ['property_id' => $this->property->id]));
        $response->assertUnauthorized();
    }
}
