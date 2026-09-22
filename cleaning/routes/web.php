<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ManageSessionController;
use App\Http\Controllers\SessionReportController;
use App\Http\Controllers\TeamController;
use App\Models\User;
use App\Models\CleaningSession;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

if (!function_exists('serveVideoStream')) {
    function serveVideoStream($fullPath, $mimeType) {
        $size = filesize($fullPath);
        $start = 0;
        $end = $size - 1;
        $length = $size;
        $status = 200;
        $headers = [
            'Content-Type' => $mimeType,
            'Accept-Ranges' => 'bytes',
        ];


        header("Content-Type: $mimeType");
        header("Accept-Ranges: bytes");
        header("Cache-Control: no-cache, private");
        header("Content-Encoding: identity");

        if (isset($_SERVER['HTTP_RANGE'])) {
            $c_start = $start;
            $c_end = $end;

            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if (strpos($range, ',') !== false) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $start-$end/$size");
                exit;
            }
            if ($range == '-') {
                $c_start = $size - substr($range, 1);
            } else {
                $range = explode('-', $range);
                $c_start = $range[0];
                $c_end = (isset($range[1]) && is_numeric($range[1]) && $range[1] !== '') ? $range[1] : $c_end;
            }
            $c_end = ($c_end > $end) ? $end : $c_end;
            if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $start-$end/$size");
                exit;
            }
            $start = $c_start;
            $end = $c_end;
            $length = $end - $start + 1;
            
            header('HTTP/1.1 206 Partial Content');
            header("Content-Length: " . $length);
            header("Content-Range: bytes $start-$end/" . $size);
        } else {
            $length = $size;
            header("Content-Length: " . $length);
        }

        $stream = @fopen($fullPath, 'rb');
        if (!$stream) {
            header('HTTP/1.1 500 Internal Server Error');
            exit;
        }

        fseek($stream, $start);
        $buffer = 1024 * 64; // 64kb
        $i = $start;
        set_time_limit(0);
        
        while (!feof($stream) && $i <= $end && !connection_aborted()) {
            $bytesToRead = $buffer;
            if (($i + $bytesToRead) > $end) {
                $bytesToRead = $end - $i + 1;
            }
            $data = fread($stream, $bytesToRead);
            echo $data;
            flush();
            $i += $bytesToRead;
        }
        fclose($stream);
        exit;
    }
}

Route::get('/', function () {
    return view('welcome');
});
Route::get('/file/{path}', function (string $path) {
    if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
        abort(404);
    }
    $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
    
    $mimeType = \Illuminate\Support\Facades\File::mimeType($fullPath);
    $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    
    if ($extension === 'mp4' && $mimeType !== 'video/mp4') {
        $mimeType = 'video/mp4';
    } elseif ($extension === 'webm' && $mimeType !== 'video/webm') {
        $mimeType = 'video/webm';
    } elseif ($extension === 'mov') {
        $mimeType = 'video/quicktime';
    }

    if (str_starts_with($mimeType, 'video/')) {
        return serveVideoStream($fullPath, $mimeType);
    }

    return response()->file($fullPath, [
        'Content-Type' => $mimeType,
        'Accept-Ranges' => 'bytes'
    ]);
})->where('path', '.*');
// Serve storage files through Laravel (bypasses symlink issues on shared hosting)
Route::get('/storage/{path}', function (string $path) {
    if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
        abort(404);
    }
    $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);

    $mimeType = \Illuminate\Support\Facades\File::mimeType($fullPath);
    $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    
    if ($extension === 'mp4' && $mimeType !== 'video/mp4') {
        $mimeType = 'video/mp4';
    } elseif ($extension === 'webm' && $mimeType !== 'video/webm') {
        $mimeType = 'video/webm';
    } elseif ($extension === 'mov') {
        $mimeType = 'video/quicktime';
    }

    if (str_starts_with($mimeType, 'video/')) {
        return serveVideoStream($fullPath, $mimeType);
    }

    return response()->file($fullPath, [
        'Content-Type' => $mimeType,
        'Accept-Ranges' => 'bytes'
    ]);
})->where('path', '.*')->name('storage.serve');

Route::get('/reports/sessions/{token}', [SessionReportController::class, 'show'])
    ->where('token', '[0-9a-z]+')
    ->name('reports.sessions.show');
Route::get('/reports/sessions/{token}/photos.zip', [SessionReportController::class, 'photosZip'])
    ->where('token', '[0-9a-z]+')
    ->name('reports.sessions.photos.zip');
Route::get('/reports/sessions/{token}/photos/download/{path}', [SessionReportController::class, 'photoDownload'])
    ->where([
        'token' => '[0-9a-z]+',
        'path' => '.*',
    ])
    ->name('reports.sessions.photo.download');
Route::get('/reports/sessions/{token}/photos/{path}', [SessionReportController::class, 'photo'])
    ->where([
        'token' => '[0-9a-z]+',
        'path' => '.*',
    ])
    ->name('reports.sessions.photo');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

// Serve FFmpeg assets through Laravel to ensure correct application/wasm MIME type
Route::get('/ffmpeg-assets/{filename}', function ($filename) {
    $fullPath = public_path("vendor/ffmpeg/{$filename}");
    if (!file_exists($fullPath)) {
        abort(404);
    }
    
    $mimeType = 'application/octet-stream';
    if (str_ends_with($filename, '.wasm')) {
        $mimeType = 'application/wasm';
    } elseif (str_ends_with($filename, '.js')) {
        $mimeType = 'application/javascript';
    }
    
    return response()->file($fullPath, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=31536000'
    ]);
})->where('filename', '.*')->name('ffmpeg.assets');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Properties
    Route::resource('properties', PropertyController::class);
    Route::post('properties/{property}/activate', [PropertyController::class, 'activate'])->name('properties.activate');

    // Rooms within properties
    Route::get('properties/{property}/rooms', [PropertyController::class, 'rooms'])->name('properties.rooms.index');
    Route::put('properties/{property}/rooms/{room}', [PropertyController::class, 'updateRoom'])->name('properties.rooms.update');
    Route::delete('properties/{property}/rooms/{room}', [PropertyController::class, 'destroyRoom'])->name('properties.rooms.destroy');

    // Tasks within rooms
    Route::get('properties/{property}/rooms/{room}/tasks', [PropertyController::class, 'tasks'])->name('properties.tasks.index');
    Route::post('properties/{property}/rooms/{room}/tasks', [PropertyController::class, 'storeTask'])->name('properties.tasks.store');
    Route::post('properties/{property}/rooms/{room}/tasks/bulk', [PropertyController::class, 'bulkStoreTask'])->name('properties.tasks.bulk-store');
    Route::put('properties/{property}/rooms/{room}/tasks/{task}', [PropertyController::class, 'updateTask'])->name('properties.tasks.update');
    Route::delete('properties/{property}/rooms/{room}/tasks/{task}', [PropertyController::class, 'detachTask'])->name('properties.tasks.detach');

    // Property-level tasks
    Route::get('properties/{property}/tasks', [PropertyController::class, 'propertyTasks'])->name('properties.property-tasks.index');
    Route::post('properties/{property}/tasks', [PropertyController::class, 'storePropertyTask'])->name('properties.property-tasks.store');
    Route::put('properties/{property}/tasks/{task}', [PropertyController::class, 'updatePropertyTask'])->name('properties.property-tasks.update');
    Route::delete('properties/{property}/tasks/{task}', [PropertyController::class, 'detachPropertyTask'])->name('properties.property-tasks.detach');

    // Property Notification Settings
    Route::get('properties/{property}/notifications/settings', [\App\Http\Controllers\PropertyNotificationSettingsController::class, 'getSettings'])->name('properties.notifications.settings');
    Route::put('properties/{property}/notifications/settings', [\App\Http\Controllers\PropertyNotificationSettingsController::class, 'updateSettings'])->name('properties.notifications.update-settings');
    Route::post('properties/{property}/notifications/recipients', [\App\Http\Controllers\PropertyNotificationSettingsController::class, 'addRecipient'])->name('properties.notifications.add-recipient');
    Route::put('properties/{property}/notifications/recipients/{recipient}', [\App\Http\Controllers\PropertyNotificationSettingsController::class, 'updateRecipient'])->name('properties.notifications.update-recipient');
    Route::delete('properties/{property}/notifications/recipients/{recipient}', [\App\Http\Controllers\PropertyNotificationSettingsController::class, 'deleteRecipient'])->name('properties.notifications.delete-recipient');
    Route::get('properties/{property}/notifications/history', [\App\Http\Controllers\PropertyNotificationSettingsController::class, 'history'])->name('properties.notifications.history');
    Route::post('properties/{property}/notifications/logs/{log}/resend', [\App\Http\Controllers\PropertyNotificationSettingsController::class, 'resend'])->name('properties.notifications.resend');

    // Housekeeper Sessions
    Route::get('/assignments', [\App\Http\Controllers\AssignmentController::class, 'index'])->name('assignments.index');
    Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::get('/sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
    Route::get('/sessions/{session}/training-required', [SessionController::class, 'trainingRequired'])->name('sessions.training_required');
    Route::post('/sessions/{session}/start', [SessionController::class, 'start'])->name('sessions.start');
    Route::post('/sessions/{session}/complete-onboarding', [SessionController::class, 'completeOnboarding'])->name('sessions.complete-onboarding');
    Route::post('/sessions/{session}/gps-override', [SessionController::class, 'grantGpsOverride'])->name('sessions.gps-override');
    Route::post('/sessions/{session}/complete', [SessionController::class, 'complete'])->name('sessions.complete');
    Route::post('/sessions/{session}/admin-close', [SessionController::class, 'adminClose'])->name('sessions.admin-close');
    Route::post('/sessions/{session}/advance-stage', [SessionController::class, 'advanceStage'])->name('sessions.advance-stage');
    Route::post('/sessions/{session}/go-back-stage', [SessionController::class, 'goBackStage'])->name('sessions.go-back-stage');
    Route::post('/sessions/{session}/save-note', [SessionController::class, 'saveNote'])->name('sessions.save-note');


    // Checklist data endpoint
    Route::get('/sessions/{session}/data', [SessionController::class, 'getData'])->name('sessions.data');
    // Instructions endpoint
    Route::get('/sessions/{session}/instructions', [SessionController::class, 'getInstructions'])->name('sessions.instructions');
    // Legacy API path kept for compatibility
    Route::get('/api/sessions/{session}/data', [SessionController::class, 'getData'])->name('sessions.data.api');
    Route::post('/sessions/{session}/rooms/{room}/tasks/{task}/toggle', [ChecklistController::class, 'toggle'])->name('checklist.toggle');
    Route::post('/sessions/{session}/rooms/{room}/tasks/{task}/note', [ChecklistController::class, 'note'])->name('checklist.note');
    Route::post('/sessions/{session}/rooms/{room}/tasks/{task}/photo', [ChecklistController::class, 'taskPhoto'])->name('checklist.photo');
    Route::delete('/sessions/{session}/rooms/{room}/tasks/{task}/photo/{photo}', [ChecklistController::class, 'deletePhoto'])->name('checklist.photo.delete');
    Route::post('/sessions/{session}/rooms/{room}/tasks/{task}/view-instruction', [ChecklistController::class, 'viewInstruction'])->name('checklist.view-instruction');

    // Property-level checklist interactions (no room)
    Route::post('/sessions/{session}/tasks/{task}/toggle', [ChecklistController::class, 'togglePropertyTask'])->name('checklist.property-task.toggle');
    Route::post('/sessions/{session}/tasks/{task}/note', [ChecklistController::class, 'notePropertyTask'])->name('checklist.property-task.note');
    Route::post('/sessions/{session}/tasks/{task}/photo', [ChecklistController::class, 'propertyTaskPhoto'])->name('checklist.property-task.photo');
    Route::delete('/sessions/{session}/tasks/{task}/photo/{photo}', [ChecklistController::class, 'deletePropertyTaskPhoto'])->name('checklist.property-task.photo.delete');
    Route::post('/sessions/{session}/tasks/{task}/view-instruction', [ChecklistController::class, 'viewPropertyTaskInstruction'])->name('checklist.property-task.view-instruction');

    // Add Session Routes (Admin/Owner)
    Route::get('/manage/sessions', [ManageSessionController::class, 'index'])->name('manage.sessions.index');
    Route::get('/manage/sessions/create', [ManageSessionController::class, 'create'])->name('manage.sessions.create');
    Route::get('/reports/training', [\App\Http\Controllers\TrainingReportController::class, 'index'])->name('reports.training.index');
    Route::get('/reports/familiarity', [\App\Http\Controllers\FamiliarityReportController::class, 'index'])->name('reports.familiarity.index');
    Route::post('/manage/sessions', [ManageSessionController::class, 'store'])->name('manage.sessions.store');
    Route::get('/manage/sessions/{session}/edit', [ManageSessionController::class, 'edit'])->name('manage.sessions.edit');
    Route::put('/manage/sessions/{session}', [ManageSessionController::class, 'update'])->name('manage.sessions.update');
    Route::delete('/manage/sessions/{session}', [ManageSessionController::class, 'destroy'])->name('manage.sessions.destroy');
    Route::get('/api/properties/{property}/sporadic-tasks', [ManageSessionController::class, 'getSporadicTasks'])->name('api.properties.sporadic-tasks');

    // Calendar Routes
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/api/calendar/events', [CalendarController::class, 'events'])->name('api.calendar.events');

    // Training Routes
    Route::get('/training', [\App\Http\Controllers\TrainingController::class, 'index'])->name('training.index');
    Route::get('/training/{type}/{id}', [\App\Http\Controllers\TrainingController::class, 'show'])->name('training.show');
    Route::post('/training/{type}/{id}/progress', [\App\Http\Controllers\TrainingController::class, 'updateProgress'])->name('training.update_progress');

    // User Management Routes
    Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [\App\Http\Controllers\UserController::class, 'create'])->name('users.create');
    Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [\App\Http\Controllers\UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{user}/deactivate', [\App\Http\Controllers\UserController::class, 'deactivate'])
        ->middleware('role:admin')
        ->name('users.deactivate');
    Route::post('/users/{user}/reactivate', [\App\Http\Controllers\UserController::class, 'reactivate'])
        ->middleware('role:admin')
        ->name('users.reactivate');

    Route::post('/users/{user}/familiarity/reset', [\App\Http\Controllers\UserFamiliarityController::class, 'resetAll'])->name('users.familiarity.reset_all');
    Route::post('/users/{user}/familiarity/reset/{task}', [\App\Http\Controllers\UserFamiliarityController::class, 'resetTask'])->name('users.familiarity.reset_task');

    // Final Additional Routes to fix 500 errors
    Route::post('properties/{property}/duplicate', [\App\Http\Controllers\PropertyDuplicateController::class, 'store'])->name('properties.duplicate');
    // Properties -> Rooms sorting
    Route::match(['post', 'patch'], 'properties/{property}/rooms/order', [\App\Http\Controllers\PropertyRoomOrderController::class, 'update'])->name('properties.rooms.order');

    // Properties -> Property Tasks sorting
    Route::match(['post', 'patch'], 'properties/{property}/tasks/order', [\App\Http\Controllers\PropertyTaskOrderController::class, 'update'])->name('properties.property-tasks.order');
    Route::post('properties/{property}/rooms', [\App\Http\Controllers\PropertyRoomController::class, 'store'])->name('properties.rooms.store');
    Route::post('properties/{property}/rooms/attach', [\App\Http\Controllers\PropertyRoomAttachController::class, 'store'])->name('properties.rooms.attach');
    Route::get('api/rooms/suggest', [\App\Http\Controllers\RoomSuggestionController::class, 'index'])->name('rooms.suggest');

    Route::match(['post', 'patch'], 'rooms/{room}/tasks/order', [\App\Http\Controllers\RoomTaskOrderController::class, 'updateForRoom'])->name('rooms.tasks.order');
    Route::post('rooms/{room}/tasks/attach', [\App\Http\Controllers\RoomTaskAttachController::class, 'store'])->name('rooms.tasks.attach');
    Route::delete('rooms/{room}/tasks/{task}', [\App\Http\Controllers\RoomController::class, 'detachTask'])->name('rooms.tasks.detach');
    Route::get('api/tasks/suggest', [\App\Http\Controllers\TaskSuggestionController::class, 'index'])->name('tasks.suggest');

    Route::get('settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
    Route::post('settings', [\App\Http\Controllers\SettingsController::class, 'update'])->name('settings.update');
    Route::get('activity', [\App\Http\Controllers\ActivityController::class, 'index'])->name('activity.index');

    // Photo Management
    Route::post('sessions/{session}/rooms/{room}/photos', [\App\Http\Controllers\PhotoController::class, 'store'])->name('photos.store');
    Route::delete('sessions/{session}/photos/{photo}', [\App\Http\Controllers\PhotoController::class, 'destroy'])->name('photos.destroy');

    // Task Media Management
    Route::post('tasks/{task}/media', [\App\Http\Controllers\TaskMediaController::class, 'store'])->name('tasks.media.store');
    Route::delete('tasks/{task}/media/{media}', [\App\Http\Controllers\TaskMediaController::class, 'destroy'])->name('tasks.media.destroy');

    // API for dynamic form loading
    Route::get('api/properties/{property}/rooms', [\App\Http\Controllers\PropertyAssignmentsApiController::class, 'rooms'])->name('api.properties.rooms');
    Route::get('api/properties/{property}/tasks', [\App\Http\Controllers\PropertyAssignmentsApiController::class, 'propertyTasks'])->name('api.properties.tasks');
    Route::get('api/properties/{property}/assigned-rooms', [\App\Http\Controllers\PropertyAssignmentsApiController::class, 'assignedRooms'])->name('api.properties.assigned-rooms');
    Route::get('api/properties/{property}/assigned-property-tasks', [\App\Http\Controllers\PropertyAssignmentsApiController::class, 'assignedPropertyTasks'])->name('api.properties.assigned-property-tasks');

    // Global Room/Task Management
    Route::post('rooms/bulk-attach-tasks', [\App\Http\Controllers\RoomController::class, 'bulkAttachTasks'])->name('rooms.bulk-attach-tasks');

    // Room Task Management Routes
    Route::get('rooms/{room}/tasks', [\App\Http\Controllers\RoomController::class, 'tasks'])->name('rooms.tasks.index');
    Route::post('rooms/{room}/tasks', [\App\Http\Controllers\RoomController::class, 'storeTask'])->name('rooms.tasks.store');
    Route::get('rooms/{room}/tasks/{task}/edit', [\App\Http\Controllers\RoomController::class, 'editTask'])->name('rooms.tasks.edit');
    Route::put('rooms/{room}/tasks/{task}', [\App\Http\Controllers\RoomController::class, 'updateTask'])->name('rooms.tasks.update');
    Route::post('rooms/{room}/tasks/bulk', [\App\Http\Controllers\RoomController::class, 'bulkStoreTask'])->name('rooms.tasks.bulk-store');

    Route::resource('rooms', \App\Http\Controllers\RoomController::class);
    Route::resource('tasks', \App\Http\Controllers\TaskController::class);

    // Resources Pages (Photos, Videos, Guides)
    Route::get('resources/photos', [\App\Http\Controllers\ResourcePageController::class, 'photos'])->name('resources.photos');
    Route::get('resources/videos', [\App\Http\Controllers\ResourcePageController::class, 'videos'])->name('resources.videos');
    Route::get('resources/guides', [\App\Http\Controllers\ResourcePageController::class, 'guides'])->name('resources.guides');

    // Admin Instructional Video Management
    Route::get('admin/videos', [\App\Http\Controllers\InstructionalVideoController::class, 'index'])->name('admin.videos.index');
    Route::get('admin/videos/create', [\App\Http\Controllers\InstructionalVideoController::class, 'create'])->name('admin.videos.create');
    Route::post('admin/videos', [\App\Http\Controllers\InstructionalVideoController::class, 'store'])->name('admin.videos.store');
    Route::get('admin/videos/{video}/edit', [\App\Http\Controllers\InstructionalVideoController::class, 'edit'])->name('admin.videos.edit');
    Route::post('admin/videos/{video}', [\App\Http\Controllers\InstructionalVideoController::class, 'update'])->name('admin.videos.update');
    Route::delete('admin/videos/{video}', [\App\Http\Controllers\InstructionalVideoController::class, 'destroy'])->name('admin.videos.destroy');
    Route::post('admin/videos/{video}/publish', [\App\Http\Controllers\InstructionalVideoController::class, 'togglePublish'])->name('admin.videos.publish');

    // Cleaner API endpoint for property videos
    Route::get('api/property-videos', [\App\Http\Controllers\ResourcesController::class, 'getPropertyVideos'])->name('api.property-videos');
});

Route::get('/scan-images-now', function () {
    echo "<pre style='background:#111; color:#0f0; padding:20px; font-family:monospace;'>";
    echo "=== cPANEL DEEP FILE SCANNER ===\n\n";

    $targetFile = 'kVNfagaJs1LZTG3qNCRpZtF4eI3roD2ga71aUmXF.png';
    $searchDir = '/home/dzm'; // The root of their cPanel

    echo "Scanning the entire cPanel account ($searchDir) for '$targetFile'...\n";
    echo "This might take a few seconds...\n\n";

    $cmd = "find " . escapeshellarg($searchDir) . " -name " . escapeshellarg($targetFile) . " 2>/dev/null";
    // Since shell_exec is disabled, we use PHP's RecursiveDirectoryIterator
    $foundPaths = [];
    try {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($searchDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
            \RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        foreach ($iterator as $file) {
            // Skip massive directories to prevent timeouts
            $path = $file->getPathname();
            if ($file->isDir()) {
                $dirName = $file->getFilename();
                if (in_array($dirName, ['vendor', 'node_modules', '.git', 'cache', 'sessions', 'logs', 'framework'])) {
                    continue;
                }
            } else {
                if ($file->getFilename() === $targetFile) {
                    $foundPaths[] = $path;
                }
            }
        }
    } catch (\Exception $e) {
        // Ignore permission denied errors
    }

    if (!empty($foundPaths)) {
        echo "<strong style='color:#ffeb3b; font-size:18px;'>[FOUND IT!] The files are hiding here:</strong>\n\n";
        foreach ($foundPaths as $fp) {
            echo $fp . "\n";
        }
        echo "\nSOLUTION: Copy all images from that folder into the new storage/app/public/task-media/ folder.\n";
    } else {
        echo "<strong style='color:red'>[NOT FOUND]</strong>\n";
    }
    echo "</pre>";
});

require __DIR__.'/auth.php';



