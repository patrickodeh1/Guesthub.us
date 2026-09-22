<?php

namespace App\Http\Controllers;

use App\Models\CleaningSession;
use App\Models\RoomPhoto;
use App\Services\ImageTimestampService;
use App\Services\PersistentPhotoStorage;
use App\Services\SmsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    public function store(Request $request, CleaningSession $session, $roomId)
    {
        $request->validate([
            'photos.*' => ['required', 'image'],
        ]);

        $room   = $session->property->rooms()->findOrFail($roomId);
        $saved  = [];

        // Get files from request - ensure we only process unique files
        $files = $request->file('photos', []);

        // Remove duplicates by comparing file content hash
        $processedHashes = [];
        foreach ($files as $file) {
            // Create a hash of the file content to detect duplicates
            $fileHash = md5_file($file->getRealPath());

            // Skip if we've already processed this file
            if (in_array($fileHash, $processedHashes)) {
                continue;
            }

            $processedHashes[] = $fileHash;

            // Ensure directory exists (some disks won't auto-create on putFile)
            Storage::disk('public')->makeDirectory('room_photos');

            // Store file (explicit disk call avoids driver-specific behavior differences)
            $filename = Storage::disk('public')->putFile('room_photos', $file);
            if (!$filename) {
                throw new \RuntimeException('Unable to store uploaded room photo.');
            }

            // Apply timestamp overlay with full metadata
            $absolutePath = Storage::disk('public')->path($filename);
            $session->loadMissing(['property', 'housekeeper']);
            ImageTimestampService::overlay(
                absolutePath: $absolutePath,
                when: now(),
                timezone: $session->property->timezone ?? config('app.timezone'),
                propertyName: $session->property->name ?? null,
                roomName: $room->name ?? null,
                cleanerName: $session->housekeeper->name ?? null,
                latitude: $session->start_latitude ? (float) $session->start_latitude : null,
                longitude: $session->start_longitude ? (float) $session->start_longitude : null,
            );

            // Persist to long-term storage if the service exists
            if (class_exists(PersistentPhotoStorage::class)) {
                PersistentPhotoStorage::persistFromPublicDisk($filename);
            }

            $photo    = $session->photos()->create([
                'room_id'     => $room->id,
                'path'        => $filename,
                'captured_at' => now(),
            ]);

            // Trigger SMS notification for first photo (once per session)
            try {
                SmsNotificationService::sendPhotoStarted($session);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('SMS notification failed for photo started: ' . $e->getMessage());
            }
            $propertyTz = $session->property->timezone ?? config('app.timezone', 'America/New_York');
            $saved[] = [
                'id'          => $photo->id,
                'url'         => url('file/' . ltrim($filename, '/')),
                'captured_at' => \App\Helpers\TimezoneHelper::toLocal($photo->captured_at, $propertyTz)->format('g:i A'),
            ];
        }

        // For AJAX calls, return JSON. The front end can add these to the gallery without reloading.
        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => count($saved) . ' photos uploaded.',
                'photos'  => $saved,
            ]);
        }

        // Fallback to standard redirect if not an AJAX request
        return back()->with('ok', count($saved) . ' photos uploaded.');
    }

    public function destroy(CleaningSession $session, RoomPhoto $photo)
    {
        // Verify the photo belongs to this session
        if ($photo->session_id !== $session->id) {
            return response()->json(['success' => false, 'message' => 'Photo not found'], 404);
        }

        // Delete file from storage (prefer persistent storage service, fallback to public disk)
        if (class_exists(PersistentPhotoStorage::class)) {
            PersistentPhotoStorage::delete($photo->path);
        } else {
            if (Storage::disk('public')->exists($photo->path)) {
                Storage::disk('public')->delete($photo->path);
            }
        }

        // Delete from database
        $photo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Photo deleted successfully',
        ]);
    }
}
