<?php

namespace App\Services;

use App\Models\CleaningSession;
use Illuminate\Support\Str;

/**
 * Centralized service for generating meaningful photo download filenames.
 *
 * Format: RoomName-DateTime-CleanerName-Number.extension
 * Example: MasterBedroom-2026-09-04-1042-JohnSmith-1.jpg
 *
 * Used consistently across individual downloads, room downloads, and ZIP archives.
 * Does NOT rename the physical storage file — only controls the HTTP response
 * and ZIP entry name.
 */
class PhotoFilenameService
{
    /**
     * Generate a meaningful download filename for a single photo.
     */
    public static function generateFilename(
        string $roomName,
        string $cleanerName,
        ?\DateTimeInterface $dateTime,
        int $sequenceNumber,
        string $extension = 'jpg',
    ): string {
        $room = self::sanitizeSegment($roomName ?: 'Property');
        $cleaner = self::sanitizeSegment($cleanerName ?: 'Unassigned');
        $dateStr = $dateTime
            ? $dateTime->format('Y-m-d-Hi')
            : now()->format('Y-m-d-Hi');
        $ext = ltrim(strtolower($extension), '.');

        return "{$room}-{$dateStr}-{$cleaner}-{$sequenceNumber}.{$ext}";
    }

    /**
     * Generate a ZIP download filename for a session.
     */
    public static function generateZipFilename(
        string $propertyName,
        string $cleanerName,
        ?\DateTimeInterface $scheduledDate,
        ?string $roomName = null,
    ): string {
        $property = self::sanitizeSegment($propertyName);
        $cleaner = self::sanitizeSegment($cleanerName ?: 'Unassigned');
        $dateStr = $scheduledDate
            ? $scheduledDate->format('m-d-Y')
            : now()->format('m-d-Y');

        if ($roomName) {
            $room = self::sanitizeSegment($roomName);
            return "{$property}-{$room}-{$dateStr}-{$cleaner}.zip";
        }

        return "{$property}-{$dateStr}-{$cleaner}.zip";
    }

    /**
     * Generate a ZIP entry name (path inside ZIP).
     */
    public static function generateZipEntryName(
        string $roomName,
        string $cleanerName,
        ?\DateTimeInterface $dateTime,
        int $sequenceNumber,
        string $extension = 'jpg',
    ): string {
        $roomDir = self::sanitizeSegment($roomName ?: 'Property');
        $filename = self::generateFilename(
            $roomName,
            $cleanerName,
            $dateTime,
            $sequenceNumber,
            $extension
        );

        return "{$roomDir}/{$filename}";
    }

    /**
     * Sanitize a name segment for safe use in filenames.
     * Removes special characters, replaces spaces/symbols with hyphens.
     */
    public static function sanitizeSegment(string $value): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_\- ]+/', '', trim($value)) ?? '';
        $clean = preg_replace('/[\s_]+/', '-', $clean);
        $clean = preg_replace('/-{2,}/', '-', $clean);
        $clean = trim($clean, '-');

        return $clean === '' ? 'Item' : $clean;
    }

    /**
     * Determine the file extension from a storage path.
     */
    public static function extensionFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $ext !== '' ? $ext : 'jpg';
    }
}
