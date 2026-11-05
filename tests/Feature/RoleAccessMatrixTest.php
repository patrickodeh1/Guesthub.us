<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Room;
use App\Models\Task;
use App\Models\User;
use App\Models\Category;
use App\Models\CategoryPage;
use App\Models\Booking;
use App\Models\CleaningSession;
use App\Models\GuestNotice;
use App\Models\InstructionStep;
use App\Models\Amenity;
use App\Models\MediaFile;
use App\Models\MediaFolder;
use App\Models\PropertyPhotoReference;
use App\Models\InstructionalVideo;
use App\Models\TaskMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Support\Navigation;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_role_access_matrix(): void
    {
        foreach (['admin', 'owner', 'company', 'manager', 'housekeeper'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('StrongPassword123!'),
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $admin->assignRole('admin');

        $ownerA = User::factory()->create([
            'email' => 'owner-a@example.com',
            'password' => Hash::make('StrongPassword123!'),
            'owner_id' => null,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $ownerA->assignRole('owner');

        $ownerB = User::factory()->create([
            'email' => 'owner-b@example.com',
            'password' => Hash::make('StrongPassword123!'),
            'owner_id' => null,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $ownerB->assignRole('owner');

        $companyC = User::factory()->create([
            'email' => 'company-c@example.com',
            'password' => Hash::make('StrongPassword123!'),
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $companyC->assignRole('company');

        $managerM = User::factory()->create([
            'email' => 'manager-m@example.com',
            'password' => Hash::make('StrongPassword123!'),
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $managerM->assignRole('manager');

        $managerM0 = User::factory()->create([
            'email' => 'manager-m0@example.com',
            'password' => Hash::make('StrongPassword123!'),
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $managerM0->assignRole('manager');

        $housekeeperA = User::factory()->create([
            'email' => 'housekeeper-a@example.com',
            'password' => Hash::make('StrongPassword123!'),
            'email_verified_at' => now(),
            'must_change_password' => false,
            'phone_number' => '+15555550101',
        ]);
        $housekeeperA->assignRole('housekeeper');
        $housekeeperA->update(['owner_id' => $ownerA->id]);
        $propertyA1 = Property::factory()->create(['name' => 'A1', 'owner_id' => $ownerA->id]);
        $propertyA2 = Property::factory()->create(['name' => 'A2', 'owner_id' => $ownerA->id]);
        $propertyB1 = Property::factory()->create(['name' => 'B1', 'owner_id' => $ownerB->id]);

        $ownerA->update(['owner_id' => $companyC->id]);
        $ownerB->update(['owner_id' => null]);
        $companyC->update(['owner_id' => null]);

        $managerM->properties()->sync([$propertyA1->id]);
        $housekeeperA->properties()->sync([$propertyA1->id]);

        $defaultRoom = Room::factory()->create(['name' => 'Shared Default Room', 'is_default' => true, 'created_by' => null]);
        $roomA = Room::factory()->create(['name' => 'A Room', 'is_default' => false, 'created_by' => $ownerA->id]);
        $roomB = Room::factory()->create(['name' => 'B Room', 'is_default' => false, 'created_by' => $ownerB->id]);
        $standaloneRoomA = Room::factory()->create(['name' => 'Owner A Standalone Room', 'is_default' => false, 'created_by' => $ownerA->id]);
        $standaloneRoomB = Room::factory()->create(['name' => 'Owner B Standalone Room', 'is_default' => false, 'created_by' => $ownerB->id]);
        $legacyRoom = Room::factory()->create(['name' => 'Legacy Standalone Room', 'is_default' => false, 'created_by' => null]);
        $roomA->forceFill(['created_by' => $ownerA->id])->saveQuietly();
        $roomB->forceFill(['created_by' => $ownerB->id])->saveQuietly();
        $standaloneRoomA->forceFill(['created_by' => $ownerA->id])->saveQuietly();
        $standaloneRoomB->forceFill(['created_by' => $ownerB->id])->saveQuietly();
        $legacyRoom->forceFill(['created_by' => null])->saveQuietly();
        $propertyA1->rooms()->attach($roomA->id, ['sort_order' => 1]);
        $propertyB1->rooms()->attach($roomB->id, ['sort_order' => 1]);

        $defaultTask = Task::factory()->create(['name' => 'Shared Default Task', 'is_default' => true, 'type' => 'room', 'created_by' => null]);
        $taskA = Task::factory()->create(['name' => 'A Task', 'is_default' => false, 'type' => 'room', 'created_by' => $ownerA->id]);
        $taskB = Task::factory()->create(['name' => 'B Task', 'is_default' => false, 'type' => 'room', 'created_by' => $ownerB->id]);
        $standaloneTaskA = Task::factory()->create(['name' => 'Owner A Standalone Task', 'is_default' => false, 'type' => 'room', 'created_by' => $ownerA->id]);
        $legacyTask = Task::factory()->create(['name' => 'Legacy Standalone Task', 'is_default' => false, 'type' => 'room', 'created_by' => null]);
        $taskA2 = Task::factory()->create(['name' => 'A2 Task', 'is_default' => false, 'type' => 'room', 'created_by' => $ownerA->id]);
        $taskA->forceFill(['created_by' => $ownerA->id])->saveQuietly();
        $taskB->forceFill(['created_by' => $ownerB->id])->saveQuietly();
        $standaloneTaskA->forceFill(['created_by' => $ownerA->id])->saveQuietly();
        $legacyTask->forceFill(['created_by' => null])->saveQuietly();
        $taskA2->forceFill(['created_by' => $ownerA->id])->saveQuietly();
        $roomA->tasks()->attach($taskA->id, ['sort_order' => 1, 'instructions' => null, 'visible_to_owner' => true, 'visible_to_housekeeper' => true]);
        $defaultRoom->tasks()->attach($defaultTask->id, ['sort_order' => 1, 'instructions' => null, 'visible_to_owner' => true, 'visible_to_housekeeper' => true]);
        $roomB->tasks()->attach($taskB->id, ['sort_order' => 1, 'instructions' => null, 'visible_to_owner' => true, 'visible_to_housekeeper' => true]);
        $propertyA1->propertyTasks()->attach($taskA->id, ['sort_order' => 1, 'instructions' => null, 'visible_to_owner' => true, 'visible_to_housekeeper' => true]);
        $propertyA2->propertyTasks()->attach($taskA2->id, ['sort_order' => 1, 'instructions' => null, 'visible_to_owner' => true, 'visible_to_housekeeper' => true]);

        $bookingA1 = Booking::factory()->create([
            'booking_id' => 'MATRIX-A1',
            'guest_name' => 'Matrix Guest A1',
            'property_id' => $propertyA1->id,
            'check_out_date' => now()->addDays(2)->toDateString(),
        ]);
        Booking::factory()->create([
            'booking_id' => 'MATRIX-A2',
            'guest_name' => 'Matrix Guest A2',
            'property_id' => $propertyA2->id,
            'check_out_date' => now()->addDays(3)->toDateString(),
        ]);
        Booking::factory()->create([
            'booking_id' => 'MATRIX-B1',
            'guest_name' => 'Matrix Guest B1',
            'property_id' => $propertyB1->id,
            'check_out_date' => now()->addDays(4)->toDateString(),
        ]);
        $sessionA = CleaningSession::factory()->create([
            'property_id' => $propertyA1->id,
            'owner_id' => $ownerA->id,
            'housekeeper_id' => $housekeeperA->id,
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
        ]);
        $sessionADelete = CleaningSession::factory()->create([
            'property_id' => $propertyA1->id,
            'owner_id' => $ownerA->id,
            'housekeeper_id' => $housekeeperA->id,
            'scheduled_date' => now()->addDays(10)->toDateString(),
            'status' => 'pending',
        ]);
        $housekeeperB = User::factory()->create([
            'email' => 'housekeeper-b@example.com',
            'password' => Hash::make('StrongPassword123!'),
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $housekeeperB->assignRole('housekeeper');
        $housekeeperB->update(['owner_id' => $ownerB->id]);
        $housekeeperB->properties()->sync([$propertyB1->id]);
        $sessionB = CleaningSession::factory()->create([
            'property_id' => $propertyB1->id,
            'owner_id' => $ownerB->id,
            'housekeeper_id' => $housekeeperB->id,
            'scheduled_date' => now()->addDays(4)->toDateString(),
            'status' => 'pending',
        ]);

        $category = Category::create([
            'title' => 'Matrix Default Category',
            'slug' => 'matrix-default-category',
            'action' => 'content',
            'active' => true,
            'is_global' => true,
        ]);
        $categoryOwnerB = Category::create([
            'title' => 'Owner B Private Category',
            'slug' => 'owner-b-private-category',
            'action' => 'content',
            'active' => true,
            'is_global' => false,
        ]);
        $categoryOwnerB->forceFill(['created_by' => $ownerB->id])->save();
        $propertyA1->categories()->attach($category->id, ['active' => true]);
        $sourcePage = CategoryPage::create([
            'property_id' => $propertyA1->id,
            'category_id' => $category->id,
            'title' => 'A1 Shared Guide',
            'content' => 'Source guide content.',
            'active' => true,
        ]);

        $noticeA1 = GuestNotice::create([
            'property_id' => $propertyA1->id,
            'title' => 'Notice A1 Marker',
            'body' => 'A1 notice body.',
            'type' => 'popup',
            'phase' => 'checkin',
            'day_scope' => 'any',
            'active' => true,
        ]);
        $noticeB1 = GuestNotice::create([
            'property_id' => $propertyB1->id,
            'title' => 'Notice B1 Marker',
            'body' => 'B1 notice body.',
            'type' => 'popup',
            'phase' => 'checkin',
            'day_scope' => 'any',
            'active' => true,
        ]);
        $globalNotice = GuestNotice::create([
            'property_id' => null,
            'title' => 'Global Notice Marker',
            'body' => 'Global notice body.',
            'type' => 'popup',
            'phase' => 'any',
            'day_scope' => 'any',
            'active' => true,
        ]);

        $folderA = MediaFolder::create(['name' => 'Owner A Folder']);
        $folderA->forceFill(['created_by' => $ownerA->id])->save();
        $folderB = MediaFolder::create(['name' => 'Owner B Folder']);
        $folderB->forceFill(['created_by' => $ownerB->id])->save();
        $fileA = MediaFile::create([
            'media_folder_id' => $folderA->id,
            'path' => 'matrix/owner-a.png',
            'original_name' => 'Owner A File Marker',
            'size' => 12,
        ]);
        $fileA->forceFill(['created_by' => $ownerA->id])->save();
        $fileB = MediaFile::create([
            'media_folder_id' => $folderB->id,
            'path' => 'matrix/owner-b.png',
            'original_name' => 'Owner B File Marker',
            'size' => 12,
        ]);
        $fileB->forceFill(['created_by' => $ownerB->id])->save();

        $videoA = InstructionalVideo::create([
            'title' => 'Owner A Video Marker',
            'video_file_path' => 'matrix/owner-a.mp4',
            'category' => 'general',
            'is_published' => false,
            'created_by' => $ownerA->id,
        ]);
        $videoB = InstructionalVideo::create([
            'title' => 'Owner B Video Marker',
            'video_file_path' => 'matrix/owner-b.mp4',
            'category' => 'general',
            'is_published' => false,
            'created_by' => $ownerB->id,
        ]);

        $photoA = PropertyPhotoReference::create([
            'property_id' => $propertyA1->id,
            'image_path' => 'matrix/photo-a.jpg',
            'caption' => 'Photo A marker',
            'sort_order' => 1,
        ]);
        $photoA->forceFill(['room_id' => $roomA->id])->save();
        $photoB = PropertyPhotoReference::create([
            'property_id' => $propertyB1->id,
            'image_path' => 'matrix/photo-b.jpg',
            'caption' => 'Photo B marker',
            'sort_order' => 1,
        ]);
        $photoDelete = PropertyPhotoReference::create([
            'property_id' => $propertyA1->id,
            'image_path' => 'matrix/photo-delete.jpg',
            'caption' => 'Photo to delete',
            'sort_order' => 2,
        ]);
        $stepA = InstructionStep::create([
            'property_id' => $propertyA1->id,
            'type' => 'checkin',
            'title' => 'A1 Instruction Marker',
            'content' => 'A1 instruction',
            'active' => true,
        ]);
        $stepB = InstructionStep::create([
            'property_id' => $propertyB1->id,
            'type' => 'checkin',
            'title' => 'B1 Instruction Marker',
            'content' => 'B1 instruction',
            'active' => true,
        ]);
        $amenityA = Amenity::create([
            'property_id' => $propertyA1->id,
            'title' => 'A1 Amenity Marker',
            'active' => true,
        ]);
        $amenityB = Amenity::create([
            'property_id' => $propertyB1->id,
            'title' => 'B1 Amenity Marker',
            'active' => true,
        ]);
        $foreignTaskMedia = TaskMedia::factory()->create(['task_id' => $taskB->id]);
        Storage::fake('public');

        $checks = [
            ['id' => 'R1a', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'admin.guest-guide.show', 'params' => [$propertyA1]], 'expected' => 200],
            ['id' => 'R1b', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'admin.guest-guide.show', 'params' => [$propertyB1]], 'expected' => 403],
            ['id' => 'R1c', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.guest-guide.show', 'params' => [$propertyA1]], 'expected' => 200],
            ['id' => 'R1d', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.guest-guide.show', 'params' => [$propertyA2]], 'expected' => 403],
            ['id' => 'R1e', 'actor' => $ownerB, 'request' => ['method' => 'get', 'route' => 'rooms.edit', 'params' => [$roomA]], 'expected' => 403],
            ['id' => 'R1f', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'rooms.edit', 'params' => [$roomA]], 'expected' => 200],
            ['id' => 'R1g', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'tasks.show', 'params' => [$taskB]], 'expected' => 403],
            ['id' => 'R2', 'actor' => $ownerA, 'request' => ['method' => 'put', 'route' => 'properties.property-tasks.update', 'params' => [$propertyA1, $taskB], 'data' => []], 'expected' => 403, 'unchanged' => fn () => Task::whereKey($taskB->id)->value('name')],
            ['id' => 'R3a', 'actor' => $ownerA, 'request' => ['method' => 'put', 'route' => 'rooms.update', 'params' => [$defaultRoom], 'data' => ['name' => 'Changed Default Room', 'min_photos' => 2]], 'expected' => 403, 'unchanged' => fn () => Room::whereKey($defaultRoom->id)->value('name')],
            ['id' => 'R3b', 'actor' => $ownerA, 'request' => ['method' => 'put', 'route' => 'tasks.update', 'params' => [$defaultTask], 'data' => ['name' => 'Changed Default Task', 'type' => 'room']], 'expected' => 403, 'unchanged' => fn () => Task::whereKey($defaultTask->id)->value('name')],
            ['id' => 'R3c', 'actor' => $admin, 'request' => ['method' => 'put', 'route' => 'rooms.update', 'params' => [$defaultRoom], 'data' => ['name' => 'Shared Default Room', 'min_photos' => 2, 'is_default' => 1]], 'expected' => 302],
            ['id' => 'R3d', 'actor' => $admin, 'request' => ['method' => 'put', 'route' => 'tasks.update', 'params' => [$defaultTask], 'data' => ['name' => 'Shared Default Task', 'type' => 'room']], 'expected' => 302],
            ['id' => 'R4a', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'rooms.edit', 'params' => [$standaloneRoomA]], 'expected' => 200],
            ['id' => 'R4b', 'actor' => $ownerB, 'request' => ['method' => 'get', 'route' => 'rooms.edit', 'params' => [$standaloneRoomA]], 'expected' => 403],
            ['id' => 'R4c', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'rooms.edit', 'params' => [$legacyRoom]], 'expected' => 403],
            ['id' => 'R4d', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'tasks.show', 'params' => [$standaloneTaskA]], 'expected' => 200],
            ['id' => 'R4e', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'tasks.show', 'params' => [$legacyTask]], 'expected' => 403],
            ['id' => 'T2', 'actor' => $ownerA, 'request' => ['method' => 'post', 'route' => 'tasks.store', 'data' => ['name' => 'B Task', 'type' => 'room']], 'expected' => 302, 'changed' => fn () => Task::where('name', 'B Task')->count()],
            ['id' => 'CB1a', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'tasks.store', 'data' => ['name' => 'Stamped Manager Task', 'type' => 'room']], 'expected' => 302, 'changed' => fn () => Task::where('name', 'Stamped Manager Task')->where('created_by', $managerM->id)->count()],
            ['id' => 'CB1b', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'rooms.store', 'data' => ['name' => 'Stamped Manager Room']], 'expected' => 302, 'changed' => fn () => Room::where('name', 'Stamped Manager Room')->where('created_by', $managerM->id)->count()],
            ['id' => 'T1', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'properties.rooms.store', 'params' => [$propertyA1], 'data' => ['name' => 'Manager Room', 'min_photos' => 2]], 'expected' => 302],
            ['id' => 'T5m', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'properties.rooms.store', 'params' => [$propertyA2], 'data' => ['name' => 'No Access Room', 'min_photos' => 2]], 'expected' => 403, 'unchanged' => fn () => Room::where('name', 'No Access Room')->count()],
            ['id' => 'T3', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'rooms.tasks.attach', 'params' => [$roomA], 'data' => ['task_ids' => [$taskB->id]]], 'expected' => 403, 'unchanged' => fn () => $roomA->tasks()->whereKey($taskB->id)->exists()],
            ['id' => 'T3b', 'actor' => $managerM, 'request' => ['method' => 'delete', 'route' => 'rooms.tasks.detach', 'params' => [$roomA, $taskB]], 'expected' => 403, 'unchanged' => fn () => Task::whereKey($taskB->id)->exists()],
            ['id' => 'T3c', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'rooms.bulk-attach-tasks', 'data' => ['room_ids' => [$roomA->id], 'task_ids' => [$taskB->id]]], 'expected' => 403, 'unchanged' => fn () => $roomA->tasks()->count()],
            ['id' => 'C1a', 'actor' => $admin, 'request' => ['method' => 'get', 'route' => 'admin.categories.index'], 'expected' => 200],
            ['id' => 'C1b', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.categories.index'], 'expected' => 403],
            ['id' => 'X1a', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.properties.create'], 'expected' => 403],
            ['id' => 'X1b', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'admin.properties.store', 'data' => []], 'expected' => 403, 'unchanged' => fn () => Property::count()],
            ['id' => 'X1c', 'actor' => $managerM, 'request' => ['method' => 'put', 'route' => 'admin.properties.update', 'params' => [$propertyA1], 'data' => []], 'expected' => 403, 'unchanged' => fn () => Property::whereKey($propertyA1->id)->value('name')],
            ['id' => 'X1d', 'actor' => $managerM, 'request' => ['method' => 'delete', 'route' => 'admin.properties.destroy', 'params' => [$propertyA1]], 'expected' => 403, 'unchanged' => fn () => Property::whereKey($propertyA1->id)->exists()],
            ['id' => 'X1i', 'actor' => $ownerA, 'request' => ['method' => 'delete', 'route' => 'admin.properties.destroy', 'params' => [$propertyA1]], 'expected' => 403, 'unchanged' => fn () => Property::whereKey($propertyA1->id)->exists()],
            ['id' => 'X1j', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'properties.activate', 'params' => [$propertyA1]], 'expected' => 403, 'unchanged' => fn () => Property::whereKey($propertyA1->id)->value('active')],
            ['id' => 'X1e', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'users.index'], 'expected' => 403],
            ['id' => 'X1f', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'users.index'], 'expected' => 302],
            ['id' => 'X1l', 'actor' => $companyC, 'request' => ['method' => 'get', 'route' => 'users.index'], 'expected' => 302],
            ['id' => 'X3a', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.settings.edit'], 'expected' => 403],
            ['id' => 'X3b', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'admin.settings.edit'], 'expected' => 403],
            ['id' => 'X3c', 'actor' => $companyC, 'request' => ['method' => 'get', 'route' => 'admin.settings.edit'], 'expected' => 403],
            ['id' => 'X3d', 'actor' => $housekeeperA, 'request' => ['method' => 'get', 'route' => 'admin.settings.edit'], 'expected' => 403],
            ['id' => 'X1g', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'properties.notifications.index', 'params' => [$propertyA1]], 'expected' => 403],
            ['id' => 'X1h', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.properties.availability.index', 'params' => [$propertyA1]], 'expected' => 403],
            ['id' => 'X1k', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'admin.properties.locks.store', 'params' => [$propertyA1], 'data' => []], 'expected' => 403, 'unchanged' => fn () => \App\Models\PropertyLock::where('property_id', $propertyA1->id)->count()],
            ['id' => 'T5a', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'rooms.index'], 'expected' => 200],
            ['id' => 'T5b', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'tasks.index'], 'expected' => 200, 'absent' => ['Owner B Standalone Task', 'B Task']],
            ['id' => 'T4', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'rooms.index'], 'expected' => 200, 'present' => ['Shared Default Room', 'Owner A Standalone Room'], 'absent' => ['Owner B Standalone Room', 'Legacy Standalone Room']],
            ['id' => 'T1a', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'tasks.index'], 'expected' => 200, 'present' => ['Shared Default Task', 'Owner A Standalone Task'], 'absent' => ['Owner B Standalone Task'], 'state' => fn ($response) => ! $response->viewData('tasks')->getCollection()->contains('id', $taskB->id), 'stateExpected' => true],
            ['id' => 'T5c', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'properties.rooms.index', 'params' => [$propertyA1]], 'expected' => 200],
            ['id' => 'T5d', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'properties.rooms.index', 'params' => [$propertyB1]], 'expected' => 403],
            ['id' => 'T5e', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'properties.property-tasks.index', 'params' => [$propertyA1]], 'expected' => 200],
            ['id' => 'T5f', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'properties.rooms.order', 'params' => [$propertyA1], 'data' => ['order' => [$roomA->id]]], 'expected' => 200],
            ['id' => 'T5g', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'rooms.tasks.order', 'params' => [$roomA], 'data' => ['order' => [$taskA->id]]], 'expected' => 200],
            ['id' => 'T5h', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'rooms.suggest', 'data' => ['q' => 'Room']], 'expected' => 200],
            ['id' => 'T5i', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'tasks.suggest', 'data' => ['q' => 'Task']], 'expected' => 200],
            ['id' => 'T5j', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'tasks.media.store', 'params' => [$taskA], 'data' => []], 'expected' => 302],
            ['id' => 'T5k', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'properties.rooms.attach', 'params' => [$propertyA1], 'data' => ['room_ids' => [$roomB->id]]], 'expected' => 403, 'unchanged' => fn () => $propertyA1->rooms()->count()],
            ['id' => 'T5l', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'properties.rooms.order', 'params' => [$propertyB1], 'data' => ['order' => [$roomB->id]]], 'expected' => 403, 'unchanged' => fn () => $propertyB1->rooms()->first()->pivot->sort_order],
            ['id' => 'T5n', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'properties.rooms.attach', 'params' => [$propertyA1], 'data' => ['room_ids' => [$roomA->id, $defaultRoom->id]]], 'expected' => 302],
            ['id' => 'T5o', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'properties.property-tasks.order', 'params' => [$propertyA1], 'data' => ['order' => [$taskA->id]]], 'expected' => 200],
            ['id' => 'T5p', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'rooms.tasks.attach', 'params' => [$roomA], 'data' => ['task_ids' => [$taskA->id]]], 'expected' => 302],
            ['id' => 'T5q', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'rooms.tasks.order', 'params' => [$roomA], 'data' => ['order' => [$taskB->id]]], 'expected' => 403, 'unchanged' => fn () => $roomA->tasks()->first()->pivot->sort_order],
            ['id' => 'T5r', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'api.properties.rooms', 'params' => [$propertyA1]], 'expected' => 200],
            ['id' => 'T5s', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'api.properties.tasks', 'params' => [$propertyA1]], 'expected' => 200],
            ['id' => 'Y1a', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'api.properties.rooms', 'params' => [$propertyA2]], 'expected' => 403],
            ['id' => 'Y1b', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'api.properties.tasks', 'params' => [$propertyB1]], 'expected' => 403],
            ['id' => 'Y1c', 'actor' => $companyC, 'request' => ['method' => 'get', 'route' => 'api.properties.assigned-property-tasks', 'params' => [$propertyA2]], 'expected' => 200],
            ['id' => 'Y1d', 'actor' => $companyC, 'request' => ['method' => 'get', 'route' => 'api.properties.assigned-rooms', 'params' => [$propertyA1]], 'expected' => 200],
            ['id' => 'S1a', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.cleaning-jobs.assign-options', 'params' => ['property_id' => $propertyA1->id]], 'expected' => 200, 'state' => fn ($response) => collect($response->json('properties'))->pluck('name')->sort()->values()->all(), 'stateExpected' => ['A1']],
            ['id' => 'S1b', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.cleaning-jobs.assign-options', 'params' => ['property_id' => $propertyB1->id]], 'expected' => 403],
            ['id' => 'S1c', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'admin.cleaning-jobs.assign-options', 'params' => ['property_id' => $propertyB1->id]], 'expected' => 403],
            ['id' => 'J2a', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'admin.cleaning-jobs.calendar-assign', 'data' => ['property_id' => $propertyB1->id, 'scheduled_date' => now()->addDays(6)->toDateString(), 'housekeeper_id' => $housekeeperB->id]], 'expected' => 403, 'unchanged' => fn () => CleaningSession::where('property_id', $propertyB1->id)->whereDate('scheduled_date', now()->addDays(6)->toDateString())->count()],
            ['id' => 'J2d', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'admin.cleaning-jobs.calendar-assign', 'data' => ['property_id' => $propertyA2->id, 'scheduled_date' => now()->addDays(12)->toDateString(), 'housekeeper_id' => $housekeeperA->id]], 'expected' => 403, 'unchanged' => fn () => CleaningSession::where('property_id', $propertyA2->id)->whereDate('scheduled_date', now()->addDays(12)->toDateString())->count()],
            ['id' => 'J2b', 'actor' => $ownerA, 'request' => ['method' => 'post', 'route' => 'admin.cleaning-jobs.calendar-assign', 'server' => ['HTTP_ACCEPT' => 'application/json'], 'data' => ['property_id' => $propertyA2->id, 'scheduled_date' => now()->addDays(7)->toDateString(), 'housekeeper_id' => $housekeeperA->id]], 'expected' => 422, 'unchanged' => fn () => CleaningSession::where('property_id', $propertyA2->id)->whereDate('scheduled_date', now()->addDays(7)->toDateString())->count()],
            ['id' => 'J2c', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'admin.cleaning-jobs.calendar-assign', 'data' => ['property_id' => $propertyA1->id, 'scheduled_date' => now()->addDays(11)->toDateString(), 'housekeeper_id' => $housekeeperA->id]], 'expected' => 200, 'changed' => fn () => CleaningSession::where('property_id', $propertyA1->id)->whereDate('scheduled_date', now()->addDays(11)->toDateString())->count()],
            ['id' => 'J3a', 'actor' => $managerM, 'request' => ['method' => 'put', 'route' => 'manage.sessions.update', 'params' => [$sessionA], 'data' => ['property_id' => $propertyA1->id, 'housekeeper_id' => $housekeeperA->id, 'scheduled_date' => $sessionA->scheduled_date->toDateString(), 'scheduled_time' => '09:00', 'status' => 'pending']], 'expected' => 302, 'state' => fn () => CleaningSession::whereKey($sessionA->id)->first()?->scheduled_time?->format('H:i'), 'stateExpected' => '09:00'],
            ['id' => 'J3b', 'actor' => $managerM, 'request' => ['method' => 'delete', 'route' => 'manage.sessions.destroy', 'params' => [$sessionADelete]], 'expected' => 302, 'changed' => fn () => CleaningSession::whereKey($sessionADelete->id)->exists()],
            ['id' => 'J3c', 'actor' => $managerM, 'request' => ['method' => 'put', 'route' => 'manage.sessions.update', 'params' => [$sessionB], 'data' => ['property_id' => $propertyB1->id, 'housekeeper_id' => $housekeeperB->id, 'scheduled_date' => $sessionB->scheduled_date->toDateString(), 'scheduled_time' => '09:00', 'status' => 'pending']], 'expected' => 403, 'unchanged' => fn () => CleaningSession::whereKey($sessionB->id)->value('scheduled_time')],
            ['id' => 'J3d', 'actor' => $managerM, 'request' => ['method' => 'delete', 'route' => 'manage.sessions.destroy', 'params' => [$sessionB]], 'expected' => 403, 'unchanged' => fn () => CleaningSession::whereKey($sessionB->id)->exists()],
            ['id' => 'J4a', 'actor' => $ownerA, 'request' => ['method' => 'post', 'route' => 'admin.cleaning-jobs.calendar-assign', 'server' => ['HTTP_ACCEPT' => 'application/json'], 'data' => ['property_id' => $propertyA1->id, 'scheduled_date' => now()->addDays(8)->toDateString(), 'housekeeper_id' => $housekeeperB->id]], 'expected' => 422, 'unchanged' => fn () => CleaningSession::where('property_id', $propertyA1->id)->whereDate('scheduled_date', now()->addDays(8)->toDateString())->count()],
            ['id' => 'J4b', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'admin.cleaning-jobs.calendar-assign', 'server' => ['HTTP_ACCEPT' => 'application/json'], 'data' => ['property_id' => $propertyA1->id, 'scheduled_date' => now()->addDays(9)->toDateString(), 'housekeeper_id' => $housekeeperB->id]], 'expected' => 422, 'unchanged' => fn () => CleaningSession::where('property_id', $propertyA1->id)->whereDate('scheduled_date', now()->addDays(9)->toDateString())->count()],
            ['id' => 'J1a', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'manage.sessions.index'], 'expected' => 200, 'state' => fn ($response) => $response->viewData('properties')->pluck('name')->sort()->values()->all(), 'stateExpected' => ['A1']],
            ['id' => 'J1b', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'manage.sessions.index', 'params' => ['property_id' => $propertyA1->id]], 'expected' => 200, 'state' => fn ($response) => $response->viewData('properties')->pluck('name')->sort()->values()->all(), 'stateExpected' => ['A1']],
            ['id' => 'J5a', 'actor' => $companyC, 'request' => ['method' => 'get', 'route' => 'manage.sessions.index'], 'expected' => 200, 'state' => fn ($response) => $response->viewData('properties')->pluck('name')->sort()->values()->all(), 'stateExpected' => ['A1', 'A2']],
            ['id' => 'J5b', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'manage.sessions.index'], 'expected' => 200, 'state' => fn ($response) => $response->viewData('properties')->pluck('name')->sort()->values()->all(), 'stateExpected' => ['A1', 'A2']],
            ['id' => 'Q1', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.notices.index'], 'expected' => 200, 'present' => ['Notice A1 Marker'], 'absent' => ['Notice B1 Marker', 'Global Notice Marker']],
            ['id' => 'Q2a', 'actor' => $managerM, 'request' => ['method' => 'put', 'route' => 'admin.notices.update', 'params' => [$globalNotice], 'data' => ['title' => 'Changed global', 'body' => 'Changed', 'type' => 'popup', 'phase' => 'any', 'day_scope' => 'any']], 'expected' => 403, 'unchanged' => fn () => GuestNotice::whereKey($globalNotice->id)->value('title')],
            ['id' => 'Q2d', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.notices.edit', 'params' => [$noticeB1]], 'expected' => 403],
            ['id' => 'Q2c', 'actor' => $managerM, 'request' => ['method' => 'put', 'route' => 'admin.notices.update', 'params' => [$noticeB1], 'data' => ['title' => 'Changed B1', 'body' => 'Changed', 'type' => 'popup', 'phase' => 'checkin', 'day_scope' => 'any']], 'expected' => 403, 'unchanged' => fn () => GuestNotice::where('title', 'Notice B1 Marker')->value('title')],
            ['id' => 'Q2e', 'actor' => $managerM, 'request' => ['method' => 'delete', 'route' => 'admin.notices.destroy', 'params' => [$noticeB1]], 'expected' => 403, 'unchanged' => fn () => GuestNotice::whereKey($noticeB1->id)->exists()],
            ['id' => 'Q2f', 'actor' => $managerM, 'request' => ['method' => 'delete', 'route' => 'admin.notices.destroy', 'params' => [$globalNotice]], 'expected' => 403, 'unchanged' => fn () => GuestNotice::whereKey($globalNotice->id)->exists()],
            ['id' => 'Q2b', 'actor' => $managerM, 'request' => ['method' => 'delete', 'route' => 'admin.notices.destroy', 'params' => [$noticeA1]], 'expected' => 302],
            ['id' => 'Q3a', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'admin.notices.store', 'data' => ['title' => 'Forbidden global', 'body' => 'No property', 'type' => 'popup', 'phase' => 'any', 'day_scope' => 'any']], 'expected' => 403, 'unchanged' => fn () => GuestNotice::where('title', 'Forbidden global')->count()],
            ['id' => 'Q3b', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'admin.notices.store', 'data' => ['property_id' => $propertyB1->id, 'title' => 'Forbidden B1', 'body' => 'Wrong property', 'type' => 'popup', 'phase' => 'any', 'day_scope' => 'any']], 'expected' => 403, 'unchanged' => fn () => GuestNotice::where('title', 'Forbidden B1')->count()],
            ['id' => 'Q3c', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'admin.notices.store', 'data' => ['property_id' => $propertyA1->id, 'title' => 'Allowed A1', 'body' => 'Good property', 'type' => 'popup', 'phase' => 'any', 'day_scope' => 'any']], 'expected' => 302, 'changed' => fn () => GuestNotice::where('title', 'Allowed A1')->where('property_id', $propertyA1->id)->count()],
            ['id' => 'Q3d', 'actor' => $admin, 'request' => ['method' => 'post', 'route' => 'admin.notices.store', 'data' => ['title' => 'Allowed Global', 'body' => 'Global notice', 'type' => 'popup', 'phase' => 'any', 'day_scope' => 'any']], 'expected' => 302, 'changed' => fn () => GuestNotice::where('title', 'Allowed Global')->whereNull('property_id')->count()],
            ['id' => 'V1', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.videos.index'], 'expected' => 200, 'absent' => ['Owner B Video Marker']],
            ['id' => 'V2', 'actor' => $ownerB, 'request' => ['method' => 'delete', 'route' => 'admin.videos.destroy', 'params' => [$videoA]], 'expected' => 403, 'unchanged' => fn () => InstructionalVideo::whereKey($videoA->id)->exists()],
            ['id' => 'V2b', 'actor' => $ownerB, 'request' => ['method' => 'post', 'route' => 'admin.videos.update', 'params' => [$videoA], 'data' => ['title' => 'Changed video', 'category' => 'general']], 'expected' => 403, 'unchanged' => fn () => InstructionalVideo::whereKey($videoA->id)->value('title')],
            ['id' => 'V2c', 'actor' => $ownerB, 'request' => ['method' => 'post', 'route' => 'admin.videos.publish', 'params' => [$videoA]], 'expected' => 403, 'unchanged' => fn () => InstructionalVideo::whereKey($videoA->id)->value('is_published')],
            ['id' => 'V2d', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'admin.videos.edit', 'params' => [$videoA]], 'expected' => 200],
            ['id' => 'V2e', 'actor' => $ownerA, 'request' => ['method' => 'post', 'route' => 'admin.videos.publish', 'params' => [$videoA]], 'expected' => 302, 'changed' => fn () => InstructionalVideo::whereKey($videoA->id)->value('is_published')],
            ['id' => 'V3a', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'admin.videos.index'], 'expected' => 200, 'present' => ['Owner A Video Marker'], 'absent' => ['Owner B Video Marker']],
            ['id' => 'V3b', 'actor' => $companyC, 'request' => ['method' => 'get', 'route' => 'admin.videos.index'], 'expected' => 200, 'present' => ['Owner A Video Marker'], 'absent' => ['Owner B Video Marker']],
            ['id' => 'M1a', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'admin.media.picker', 'params' => ['folder_id' => $folderA->id]], 'expected' => 200, 'present' => ['Owner A File Marker'], 'absent' => ['Owner B File Marker']],
            ['id' => 'M1b', 'actor' => $ownerB, 'request' => ['method' => 'get', 'route' => 'admin.media.picker', 'params' => ['folder_id' => $folderB->id]], 'expected' => 200, 'present' => ['Owner B File Marker'], 'absent' => ['Owner A File Marker']],
            ['id' => 'M1c', 'actor' => $admin, 'request' => ['method' => 'get', 'route' => 'admin.media.index', 'params' => ['folder_id' => $folderA->id]], 'expected' => 200, 'present' => ['Owner A File Marker']],
            ['id' => 'M1c2', 'actor' => $admin, 'request' => ['method' => 'get', 'route' => 'admin.media.index', 'params' => ['folder_id' => $folderB->id]], 'expected' => 200, 'present' => ['Owner B File Marker']],
            ['id' => 'M2a', 'actor' => $ownerA, 'request' => ['method' => 'delete', 'route' => 'admin.media.files.destroy', 'params' => [$fileB]], 'expected' => 403, 'unchanged' => fn () => MediaFile::whereKey($fileB->id)->exists()],
            ['id' => 'M2b', 'actor' => $ownerA, 'request' => ['method' => 'post', 'route' => 'admin.media.bulk-move', 'data' => ['file_ids' => [$fileB->id], 'target_folder_id' => null]], 'expected' => 302, 'unchanged' => fn () => MediaFile::whereKey($fileB->id)->value('media_folder_id')],
            ['id' => 'M2c', 'actor' => $ownerA, 'request' => ['method' => 'delete', 'route' => 'admin.media.folders.destroy', 'params' => [$folderB]], 'expected' => 403, 'unchanged' => fn () => MediaFolder::whereKey($folderB->id)->exists()],
            ['id' => 'M3b', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'admin.media.folders.store', 'data' => ['name' => 'Manager Folder Marker']], 'expected' => 302, 'changed' => fn () => MediaFolder::where('name', 'Manager Folder Marker')->where('created_by', $managerM->id)->count()],
            ['id' => 'M3a', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'admin.media.files.store', 'files' => ['image' => UploadedFile::fake()->image('Manager Upload Marker.png')]], 'expected' => 302, 'changed' => fn () => MediaFile::where('original_name', 'Manager Upload Marker.png')->where('created_by', $managerM->id)->count()],
            ['id' => 'M3c', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.media.index'], 'expected' => 200, 'present' => ['Manager Upload Marker.png'], 'absent' => ['Owner B File Marker']],
            ['id' => 'M4', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.media.index'], 'expected' => 200],
            ['id' => 'M4b', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'tasks.media.store', 'params' => [$taskA], 'files' => ['media' => [UploadedFile::fake()->image('Task Media Marker.png')]]], 'expected' => 302, 'changed' => fn () => TaskMedia::where('task_id', $taskA->id)->exists()],
            ['id' => 'C2a', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'admin.categories.assign', 'data' => ['property_id' => $propertyA1->id, 'category_ids' => [$category->id, $categoryOwnerB->id]]], 'expected' => 302, 'state' => fn () => $propertyA1->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all(), 'stateExpected' => [(int) $category->id]],
            ['id' => 'C2d', 'actor' => $ownerA, 'request' => ['method' => 'post', 'route' => 'admin.categories.assign', 'data' => ['property_id' => $propertyA1->id, 'category_ids' => [$category->id]]], 'expected' => 302, 'state' => fn () => $propertyA1->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all(), 'stateExpected' => [(int) $category->id]],
            ['id' => 'C2b', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'admin.categories.assign', 'data' => ['property_id' => $propertyA2->id, 'category_ids' => [$category->id]]], 'expected' => 403, 'unchanged' => fn () => $propertyA2->categories()->count()],
            ['id' => 'C2c', 'actor' => $ownerA, 'request' => ['method' => 'post', 'route' => 'admin.categories.assign', 'data' => ['property_id' => $propertyB1->id, 'category_ids' => [$category->id]]], 'expected' => 403, 'unchanged' => fn () => $propertyB1->categories()->count()],
            ['id' => 'C1c', 'actor' => $ownerA, 'request' => ['method' => 'post', 'route' => 'admin.categories.reorder', 'data' => ['category_ids' => [$category->id]]], 'expected' => 403, 'unchanged' => fn () => Category::whereKey($category->id)->value('sort_order')],
            ['id' => 'C1d', 'actor' => $ownerA, 'request' => ['method' => 'post', 'route' => 'admin.categories.store', 'data' => ['title' => 'Forbidden Category']], 'expected' => 403, 'unchanged' => fn () => Category::where('title', 'Forbidden Category')->count()],
            ['id' => 'C1f', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.categories.create'], 'expected' => 403],
            ['id' => 'C1g', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.categories.edit', 'params' => [$category]], 'expected' => 403],
            ['id' => 'C1h', 'actor' => $managerM, 'request' => ['method' => 'put', 'route' => 'admin.categories.update', 'params' => [$category], 'data' => ['title' => 'Changed category']], 'expected' => 403, 'unchanged' => fn () => Category::whereKey($category->id)->value('title')],
            ['id' => 'C1i', 'actor' => $managerM, 'request' => ['method' => 'delete', 'route' => 'admin.categories.destroy', 'params' => [$category]], 'expected' => 403, 'unchanged' => fn () => Category::whereKey($category->id)->exists()],
            ['id' => 'C5', 'actor' => $admin, 'request' => ['method' => 'post', 'route' => 'admin.properties.store', 'data' => ['name' => 'Matrix Category Property', 'address' => '1 Matrix Way', 'city' => 'Test City', 'owner_id' => $ownerA->id]], 'expected' => 302, 'state' => function () {
                $property = Property::where('name', 'Matrix Category Property')->first();

                return $property
                    ? $property->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->sort()->values()->all()
                    : [];
            }, 'stateExpected' => Category::whereNull('created_by')->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all()],
            ['id' => 'C3', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'admin.categories.preview', 'params' => [$category, $propertyA1]], 'expected' => 200],
            ['id' => 'C1e', 'actor' => $admin, 'request' => ['method' => 'post', 'route' => 'admin.categories.store', 'data' => ['title' => 'Admin Created Category']], 'expected' => 302, 'changed' => fn () => Category::where('title', 'Admin Created Category')->whereNull('created_by')->count()],
            ['id' => 'G4a', 'actor' => $ownerA, 'request' => ['method' => 'delete', 'route' => 'admin.photo-guide.destroy', 'params' => [$photoB]], 'expected' => 403, 'unchanged' => fn () => PropertyPhotoReference::whereKey($photoB->id)->exists()],
            ['id' => 'G4b', 'actor' => $managerM, 'request' => ['method' => 'post', 'route' => 'admin.photo-guide.update', 'params' => [$photoB], 'data' => ['instructions' => 'changed']], 'expected' => 403, 'unchanged' => fn () => PropertyPhotoReference::whereKey($photoB->id)->value('instructions')],
            ['id' => 'G4c', 'actor' => $ownerA, 'request' => ['method' => 'post', 'route' => 'admin.photo-guide.update', 'params' => [$photoA], 'data' => ['instructions' => 'Owner A instructions']], 'expected' => 302, 'state' => fn () => PropertyPhotoReference::whereKey($photoA->id)->value('instructions'), 'stateExpected' => 'Owner A instructions'],
            ['id' => 'G4d', 'actor' => $ownerA, 'request' => ['method' => 'delete', 'route' => 'admin.photo-guide.destroy', 'params' => [$photoDelete]], 'expected' => 302, 'changed' => fn () => PropertyPhotoReference::whereKey($photoDelete->id)->exists()],
            ['id' => 'G3a', 'actor' => $ownerA, 'request' => ['method' => 'post', 'route' => 'admin.photo-guide.link', 'params' => [$propertyA1], 'data' => ['source_id' => $propertyB1->id]], 'expected' => 422, 'unchanged' => fn () => Property::whereKey($propertyA1->id)->value('photo_guide_source_id')],
            ['id' => 'G1', 'actor' => $admin, 'request' => ['method' => 'post', 'route' => 'admin.guest-guide.copy', 'params' => [$propertyA1], 'data' => ['target_property_ids' => [$propertyA2->id, $propertyB1->id], 'sections' => [$category->id => 'sync']]], 'expected' => 302, 'state' => fn () => [
                'same_owner_link' => CategoryPage::where('property_id', $propertyA2->id)->where('category_id', $category->id)->value('linked_page_id'),
                'cross_owner_link' => CategoryPage::where('property_id', $propertyB1->id)->where('category_id', $category->id)->value('linked_page_id'),
            ], 'stateExpected' => ['same_owner_link' => $sourcePage->id, 'cross_owner_link' => null]],
            ['id' => 'G2', 'actor' => $admin, 'request' => ['method' => 'put', 'route' => 'admin.content.update', 'params' => [$propertyA1, $category], 'data' => ['title' => 'Updated Shared Guide', 'content' => 'Updated source content.', 'active' => 1, 'apply_to_property_ids' => [$propertyA2->id, $propertyB1->id], 'apply_mode' => 'sync']], 'expected' => 302, 'state' => fn () => [
                'same_owner_link' => CategoryPage::where('property_id', $propertyA2->id)->where('category_id', $category->id)->value('linked_page_id'),
                'cross_owner_link' => CategoryPage::where('property_id', $propertyB1->id)->where('category_id', $category->id)->value('linked_page_id'),
            ], 'stateExpected' => ['same_owner_link' => CategoryPage::where('property_id', $propertyA1->id)->where('category_id', $category->id)->value('id'), 'cross_owner_link' => null]],
            ['id' => 'G3b', 'actor' => $ownerA, 'request' => ['method' => 'post', 'route' => 'admin.photo-guide.link', 'params' => [$propertyA2], 'data' => ['source_id' => $propertyA1->id]], 'expected' => 302, 'state' => fn () => Property::whereKey($propertyA2->id)->value('photo_guide_source_id'), 'stateExpected' => $propertyA1->id],
            ['id' => 'I1', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.instructions.index'], 'expected' => 200, 'present' => ['A1'], 'absent' => ['B1']],
            ['id' => 'I2a', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.instructions.edit', 'params' => [$stepB]], 'expected' => 403],
            ['id' => 'I2b', 'actor' => $ownerA, 'request' => ['method' => 'delete', 'route' => 'admin.instructions.destroy', 'params' => [$stepB]], 'expected' => 403, 'unchanged' => fn () => InstructionStep::whereKey($stepB->id)->exists()],
            ['id' => 'I2c', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.instructions.edit', 'params' => [$stepA]], 'expected' => 200],
            ['id' => 'I2d', 'actor' => $managerM, 'request' => ['method' => 'put', 'route' => 'admin.instructions.update', 'params' => [$stepA], 'data' => ['property_id' => $propertyA1->id, 'type' => 'checkin', 'title' => 'A1 Instruction Marker', 'content' => 'updated']], 'expected' => 302],
            ['id' => 'I2e', 'actor' => $ownerA, 'request' => ['method' => 'put', 'route' => 'admin.instructions.update', 'params' => [$stepB], 'data' => ['property_id' => $propertyB1->id, 'type' => 'checkin', 'title' => 'Blocked B1 Step']], 'expected' => 403, 'unchanged' => fn () => InstructionStep::whereKey($stepB->id)->value('title')],
            ['id' => 'I3a', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.amenities.edit', 'params' => [$amenityB]], 'expected' => 403],
            ['id' => 'I3b', 'actor' => $ownerA, 'request' => ['method' => 'delete', 'route' => 'admin.amenities.destroy', 'params' => [$amenityB]], 'expected' => 403, 'unchanged' => fn () => Amenity::whereKey($amenityB->id)->exists()],
            ['id' => 'I3c', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'admin.amenities.edit', 'params' => [$amenityA]], 'expected' => 200],
            ['id' => 'I3d', 'actor' => $managerM, 'request' => ['method' => 'put', 'route' => 'admin.amenities.update', 'params' => [$amenityB], 'data' => ['title' => 'Blocked B1 Amenity']], 'expected' => 403, 'unchanged' => fn () => Amenity::whereKey($amenityB->id)->value('title')],
            ['id' => 'I3e', 'actor' => $managerM, 'request' => ['method' => 'put', 'route' => 'admin.amenities.update', 'params' => [$amenityA], 'data' => ['title' => 'Updated A1 Amenity']], 'expected' => 302, 'state' => fn () => Amenity::whereKey($amenityA->id)->value('title'), 'stateExpected' => 'Updated A1 Amenity'],
            ['id' => 'I3f', 'actor' => $ownerA, 'request' => ['method' => 'delete', 'route' => 'admin.amenities.destroy', 'params' => [$amenityA]], 'expected' => 302, 'changed' => fn () => Amenity::whereKey($amenityA->id)->exists()],
            ['id' => 'T3d', 'actor' => $managerM, 'request' => ['method' => 'delete', 'route' => 'tasks.media.destroy', 'params' => [$taskA, $foreignTaskMedia]], 'expected' => 404, 'unchanged' => fn () => TaskMedia::whereKey($foreignTaskMedia->id)->exists()],
            ['id' => 'D1', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'dashboard'], 'expected' => 200, 'state' => fn ($response) => collect($response->viewData('checkoutBoard')['groups'])->flatMap(fn ($rows) => $rows)->pluck('property.name')->unique()->sort()->values()->all(), 'stateExpected' => ['A1', 'A2']],
            ['id' => 'D2', 'actor' => $companyC, 'request' => ['method' => 'get', 'route' => 'dashboard'], 'expected' => 200, 'state' => fn ($response) => collect($response->viewData('checkoutBoard')['groups'])->flatMap(fn ($rows) => $rows)->pluck('property.name')->unique()->sort()->values()->all(), 'stateExpected' => ['A1', 'A2']],
            ['id' => 'D3', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'dashboard'], 'expected' => 200, 'state' => fn ($response) => collect($response->viewData('checkoutBoard')['groups'])->flatMap(fn ($rows) => $rows)->pluck('property.name')->unique()->sort()->values()->all(), 'stateExpected' => ['A1']],
            ['id' => 'D4', 'actor' => $managerM0, 'request' => ['method' => 'get', 'route' => 'dashboard'], 'expected' => 200, 'state' => fn ($response) => collect($response->viewData('checkoutBoard')['groups'])->flatMap(fn ($rows) => $rows)->pluck('property.name')->unique()->sort()->values()->all(), 'stateExpected' => []],
            ['id' => 'D5', 'actor' => $housekeeperA, 'request' => ['method' => 'get', 'route' => 'dashboard'], 'expected' => 200, 'state' => fn ($response) => [($sessions = $response->viewData('upcomingSessions')->concat($response->viewData('pendingOffers')))->contains('id', $sessionA->id), $sessions->every(fn ($s) => (int) $s->housekeeper_id === (int) $housekeeperA->id)], 'stateExpected' => [true, true]],
            ['id' => 'D6', 'actor' => $admin, 'request' => ['method' => 'get', 'route' => 'dashboard'], 'expected' => 200, 'state' => fn ($response) => collect($response->viewData('checkoutBoard')['groups'])->flatMap(fn ($rows) => $rows)->pluck('property.name')->unique()->sort()->values()->all(), 'stateExpected' => ['A1', 'A2', 'B1']],
            ['id' => 'U1', 'actor' => $admin, 'request' => ['method' => 'get', 'route' => 'guest.show', 'params' => [$bookingA1->booking_id, $bookingA1->token]], 'expected' => 200],
            ['id' => 'K1a', 'actor' => $housekeeperA, 'request' => ['method' => 'get', 'route' => 'admin.settings.edit'], 'expected' => 403],
            ['id' => 'K1c', 'actor' => $housekeeperA, 'request' => ['method' => 'get', 'route' => 'sessions.show', 'params' => [$sessionA]], 'expected' => 200],
            ['id' => 'K1b', 'actor' => $housekeeperA, 'request' => ['method' => 'get', 'route' => 'sessions.show', 'params' => [$sessionB]], 'expected' => 403],
            ['id' => 'P1a', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'reports.training.index'], 'expected' => 200, 'state' => fn ($response) => $response->viewData('cleaners')->pluck('id')->map(fn ($id) => (int) $id)->all(), 'stateExpected' => [(int) $housekeeperA->id]],
            ['id' => 'P1b', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'reports.training.index'], 'expected' => 200, 'state' => fn ($response) => $response->viewData('cleaners')->pluck('id')->map(fn ($id) => (int) $id)->all(), 'stateExpected' => [(int) $housekeeperA->id]],
            ['id' => 'P1c', 'actor' => $companyC, 'request' => ['method' => 'get', 'route' => 'reports.training.index'], 'expected' => 200, 'state' => fn ($response) => $response->viewData('cleaners')->pluck('id')->map(fn ($id) => (int) $id)->all(), 'stateExpected' => [(int) $housekeeperA->id]],
            ['id' => 'P1d', 'actor' => $admin, 'request' => ['method' => 'get', 'route' => 'reports.training.index'], 'expected' => 200, 'state' => fn ($response) => $response->viewData('cleaners')->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(), 'stateExpected' => [(int) $housekeeperA->id, (int) $housekeeperB->id]],
            ['id' => 'P2a', 'actor' => $ownerA, 'request' => ['method' => 'get', 'route' => 'reports.familiarity.index'], 'expected' => 200, 'state' => fn ($response) => $response->viewData('cleaners')->pluck('id')->map(fn ($id) => (int) $id)->all(), 'stateExpected' => [(int) $housekeeperA->id]],
            ['id' => 'P2b', 'actor' => $managerM, 'request' => ['method' => 'get', 'route' => 'reports.familiarity.index'], 'expected' => 200, 'state' => fn ($response) => $response->viewData('cleaners')->pluck('id')->map(fn ($id) => (int) $id)->all(), 'stateExpected' => [(int) $housekeeperA->id]],
            ['id' => 'P2c', 'actor' => $managerM0, 'request' => ['method' => 'get', 'route' => 'reports.familiarity.index'], 'expected' => 200, 'state' => fn ($response) => $response->viewData('cleaners')->pluck('id')->all(), 'stateExpected' => []],
            ['id' => 'P2d', 'actor' => $ownerB, 'request' => ['method' => 'get', 'route' => 'reports.familiarity.index'], 'expected' => 200, 'state' => fn ($response) => $response->viewData('cleaners')->pluck('id')->map(fn ($id) => (int) $id)->all(), 'stateExpected' => [(int) $housekeeperB->id]],
            ['id' => 'U2a', 'actor' => $admin, 'request' => ['method' => 'get', 'route' => 'guest.show', 'params' => [$bookingA1->booking_id, 'wrong-token']], 'expected' => 404],
            ['id' => 'T6', 'actor' => $admin, 'request' => ['method' => 'get', 'route' => 'rooms.index'], 'expected' => 200],
        ];

        $failures = [];
        $allItems = function (array $items) use (&$allItems): array {
            $flat = [];
            foreach ($items as $item) {
                $flat[] = $item;
                $flat = array_merge($flat, $allItems($item['children'] ?? []));
            }

            return $flat;
        };
        $navigationChecks = [
            [$admin, 'Users', true, 'admin'],
            [$ownerA, 'Users', true, 'owner'],
            [$companyC, 'Users', true, 'company'],
            [$managerM, 'Users', false, 'manager'],
            [$housekeeperA, 'Users', false, 'housekeeper'],
            [$admin, 'Activity Logs', true, 'admin'],
            [$managerM, 'Activity Logs', true, 'manager'],
            [$ownerA, 'Activity Logs', true, 'owner'],
            [$companyC, 'Activity Logs', true, 'company'],
            [$admin, 'Payments', true, 'admin'],
            [$ownerA, 'Payments', true, 'owner'],
            [$companyC, 'Payments', true, 'company'],
            [$managerM, 'Payments', true, 'manager'],
            [$admin, 'Categories', true, 'admin'],
            [$ownerA, 'Categories', false, 'owner'],
            [$companyC, 'Categories', false, 'company'],
            [$managerM, 'Categories', false, 'manager'],
            [$housekeeperA, 'Categories', false, 'housekeeper'],
            [$admin, 'Settings', true, 'admin'],
            [$ownerA, 'Settings', true, 'owner'],
            [$companyC, 'Settings', true, 'company'],
            [$managerM, 'Settings', true, 'manager'],
            [$housekeeperA, 'Settings', false, 'housekeeper'],
            [$admin, 'Media Library', true, 'admin'],
            [$ownerA, 'Media Library', true, 'owner'],
            [$companyC, 'Media Library', true, 'company'],
            [$managerM, 'Media Library', true, 'manager'],
            [$admin, 'Video Library', true, 'admin'],
            [$ownerA, 'Video Library', true, 'owner'],
            [$companyC, 'Video Library', true, 'company'],
            [$managerM, 'Video Library', true, 'manager'],
            [$admin, 'Cleaning Tasks', true, 'admin'],
            [$ownerA, 'Cleaning Tasks', true, 'owner'],
            [$companyC, 'Cleaning Tasks', true, 'company'],
            [$managerM, 'Cleaning Tasks', true, 'manager'],
            [$admin, 'Cleaning Rooms', true, 'admin'],
            [$ownerA, 'Cleaning Rooms', true, 'owner'],
            [$companyC, 'Cleaning Rooms', true, 'company'],
            [$managerM, 'Cleaning Rooms', true, 'manager'],
        ];
        foreach ($navigationChecks as [$actor, $label, $expected, $role]) {
            $sections = app(Navigation::class)->forUser($actor);
            $labels = collect($sections)->flatMap(fn ($section) => $allItems($section['items']))->pluck('label');
            $actual = $labels->contains($label);
            if ($actual !== $expected) {
                $failures[] = [
                    'id' => 'N1',
                    'actor' => $role,
                    'request' => 'sidebar: '.$label,
                    'expected' => $expected ? 'visible' : 'hidden',
                    'actual' => $actual ? 'visible' : 'hidden',
                ];
            }
        }

        $resourceParameters = [
            'property' => $propertyA1,
            'session' => $sessionA,
            'cleaningSession' => $sessionA,
            'room' => $roomA,
            'roomId' => $roomA->id,
            'task' => $taskA,
            'booking' => $bookingA1,
            'category' => $category,
            'instruction' => $stepA,
            'amenity' => $amenityA,
            'reference' => $photoA,
            'video' => $videoA,
            'file' => $fileA,
            'folder' => $folderA,
        ];
        $protectedParameters = ['property', 'session', 'cleaningSession', 'room', 'roomId', 'task', 'booking'];
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $routeDefinition) {
            $routeName = $routeDefinition->getName();
            $parameterNames = $routeDefinition->parameterNames();
            if (! $routeName
                || ! in_array('GET', $routeDefinition->methods(), true)
                || ! array_intersect($parameterNames, $protectedParameters)
                || array_diff($parameterNames, array_keys($resourceParameters))) {
                continue;
            }

            $routeParameters = array_intersect_key($resourceParameters, array_flip($parameterNames));
            $response = $this->actingAs($ownerB)->get(route($routeName, $routeParameters));
            if (! in_array($response->getStatusCode(), [403, 404], true)) {
                $failures[] = [
                    'id' => 'R1-route-'.$routeName,
                    'actor' => $ownerB->email,
                    'request' => $routeName,
                    'expected' => '403 or 404 for owner A resource',
                    'actual' => (string) $response->getStatusCode(),
                ];
            }
        }

        foreach ($checks as $check) {
            $before = isset($check['unchanged']) || isset($check['changed'])
                ? ($check['unchanged'] ?? $check['changed'])()
                : null;
            $response = $this->actingAs($check['actor'])->call(
                $check['request']['method'],
                route($check['request']['route'], $check['request']['params'] ?? []),
                $check['request']['data'] ?? [],
                [],
                $check['request']['files'] ?? [],
                $check['request']['server'] ?? []
            );

            $actual = $response->getStatusCode();
            $after = isset($check['unchanged']) || isset($check['changed'])
                ? ($check['unchanged'] ?? $check['changed'])()
                : null;
            $stateMismatch = isset($check['unchanged']) && $before !== $after
                || isset($check['changed']) && $before === $after;
            $actualState = isset($check['state']) && $actual < 400 ? $check['state']($response) : null;
            $stateMismatch = $stateMismatch
                || (isset($check['state']) && ($actual >= 400 || $actualState !== $check['stateExpected']));
            $content = (string) $response->getContent();
            $missingContent = array_values(array_filter($check['present'] ?? [], fn ($value) => ! str_contains($content, $value)));
            $unexpectedContent = array_values(array_filter($check['absent'] ?? [], fn ($value) => str_contains($content, $value)));
            if ((int) $actual !== (int) $check['expected'] || $stateMismatch || $missingContent || $unexpectedContent) {
                $expectedResult = (string) $check['expected'];
                $actualResult = (string) $actual;
                if (isset($check['unchanged'])) {
                    $expectedResult .= ' and unchanged';
                    $actualResult .= ' and '.($before === $after ? 'unchanged' : 'changed');
                } elseif (isset($check['changed'])) {
                    $expectedResult .= ' and changed';
                    $actualResult .= ' and '.($before === $after ? 'unchanged' : 'changed');
                }
                if (isset($check['state'])) {
                    $expectedResult .= ' with state '.json_encode($check['stateExpected']);
                    $actualResult .= ' with state '.json_encode($actualState);
                }
                if ($missingContent || $unexpectedContent) {
                    $expectedResult .= ' with content markers present '.json_encode($check['present'] ?? []).' and absent '.json_encode($check['absent'] ?? []);
                    $actualResult .= ' (missing: '.implode(', ', $missingContent).'; unexpected: '.implode(', ', $unexpectedContent).')';
                }

                $failures[] = [
                    'id' => $check['id'],
                    'actor' => $check['actor']->email,
                    'request' => $check['request']['route'],
                    'expected' => $expectedResult,
                    'actual' => $actualResult.(($ex = $response->baseResponse->exception ?? null) && $actual >= 400 ? ' ['.class_basename($ex).': '.$ex->getMessage().']' : ''),
                ];
            }
        }

        Storage::disk('public')->put($photoA->image_path, 'source photo');
        $propertyB1->forceFill(['photo_guide_source_id' => $propertyA1->id])->save();
        $legacyLinkedPage = CategoryPage::firstOrNew([
            'property_id' => $propertyB1->id,
            'category_id' => $category->id,
        ]);
        $legacyLinkedPage->forceFill([
            'title' => 'Legacy linked title',
            'content' => 'Legacy linked content',
            'linked_page_id' => $sourcePage->id,
            'active' => true,
        ])->save();

        $beforeDryRun = [
            'photo_source_id' => $propertyB1->fresh()->photo_guide_source_id,
            'page_link_id' => $legacyLinkedPage->fresh()->linked_page_id,
            'photo_count' => PropertyPhotoReference::where('property_id', $propertyB1->id)->count(),
        ];
        $dryRunStatus = Artisan::call('guides:unlink-foreign', ['--dry-run' => true]);
        $dryRunOutput = Artisan::output();
        $afterDryRun = [
            'photo_source_id' => $propertyB1->fresh()->photo_guide_source_id,
            'page_link_id' => $legacyLinkedPage->fresh()->linked_page_id,
            'photo_count' => PropertyPhotoReference::where('property_id', $propertyB1->id)->count(),
        ];
        if ($dryRunStatus !== 0 || ! str_contains($dryRunOutput, 'DRY RUN: nothing will be changed.')
            || $beforeDryRun !== $afterDryRun) {
            $failures[] = [
                'id' => 'G5a',
                'actor' => 'artisan',
                'request' => 'guides:unlink-foreign --dry-run',
                'expected' => 'success, reports candidates, and leaves data unchanged',
                'actual' => 'exit '.$dryRunStatus.'; '.json_encode($afterDryRun),
            ];
        }

        $convertStatus = Artisan::call('guides:unlink-foreign');
        $convertedPage = $legacyLinkedPage->fresh();
        $copiedPhoto = PropertyPhotoReference::where('property_id', $propertyB1->id)
            ->where('image_path', '!=', 'matrix/photo-b.jpg')
            ->first();
        $conversionState = [
            'property_link_cleared' => $propertyB1->fresh()->photo_guide_source_id === null,
            'category_page_unlinked' => $convertedPage->linked_page_id === null,
            'category_content_copied' => $convertedPage->content === $sourcePage->fresh()->content,
            'photo_copied' => $copiedPhoto !== null,
            'photo_room_unmatched_to_zero' => $copiedPhoto && (int) $copiedPhoto->room_id === 0,
            'photo_file_copied' => $copiedPhoto && Storage::disk('public')->exists($copiedPhoto->image_path),
            'same_owner_link_preserved' => (int) $propertyA2->fresh()->photo_guide_source_id === (int) $propertyA1->id,
        ];
        if ($convertStatus !== 0 || in_array(false, $conversionState, true)) {
            $failures[] = [
                'id' => 'G5b',
                'actor' => 'artisan',
                'request' => 'guides:unlink-foreign',
                'expected' => 'cross-owner links copied and removed; same-owner link preserved',
                'actual' => 'exit '.$convertStatus.'; '.json_encode($conversionState),
            ];
        }

        $secondRunStatus = Artisan::call('guides:unlink-foreign');
        $secondRunOutput = Artisan::output();
        if ($secondRunStatus !== 0 || ! str_contains($secondRunOutput, '| Photo guide links between different owners')
            || ! str_contains($secondRunOutput, '| 0     |')) {
            $failures[] = [
                'id' => 'G5c',
                'actor' => 'artisan',
                'request' => 'guides:unlink-foreign (second run)',
                'expected' => 'success and zero cross-owner conversions',
                'actual' => 'exit '.$secondRunStatus.'; '.$secondRunOutput,
            ];
        }

        if (str_contains($convertedPage->title, 'A1') || str_contains($convertedPage->content, 'A1')
            || str_contains($convertedPage->title, 'B1') || str_contains($convertedPage->content, 'B1')) {
            $failures[] = [
                'id' => 'G6',
                'actor' => 'system',
                'request' => 'converted guide content',
                'expected' => 'no reference to another property name',
                'actual' => $convertedPage->title.' / '.$convertedPage->content,
            ];
        }

        if (file_exists(resource_path('views/guest/verify.blade.php'))) {
            $failures[] = [
                'id' => 'U3',
                'actor' => 'system',
                'request' => 'resources/views/guest/verify.blade.php',
                'expected' => 'file absent',
                'actual' => 'file exists',
            ];
        }

        $formatted = collect($failures)
            ->map(fn (array $failure) => '| '.$failure['id'].' | '.$failure['actor'].' | '.$failure['request'].' | '.$failure['expected'].' | '.$failure['actual'].' |')
            ->implode("\n");
        $this->assertEmpty(
            $failures,
            "Collected role-access failures:\n| ID | Actor | Request | Expected | Actual |\n|---|---|---|---|---|\n{$formatted}"
        );
    }
}
