<?php

namespace App\Services;

use App\Helpers\TimezoneHelper;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Typography\FontFactory;
use Throwable;

/**
 * Permanently embeds identifying metadata into photo pixels.
 *
 * Information embedded: Property Name, Room Name, Date/Time (property-local TZ),
 * Cleaner Name, and GPS coordinates (when available).
 *
 * Uses a semi-transparent dark background panel behind the text so information
 * remains readable on both light and dark photos.
 */
class ImageTimestampService
{
    /**
     * Structured data transfer object for overlay metadata.
     *
     * Designed so that future per-photo GPS can be passed without rewriting
     * the service interface.
     */
    public static function overlay(
        string $absolutePath,
        \DateTimeInterface $when,
        ?string $timezone = null,
        ?string $propertyName = null,
        ?string $roomName = null,
        ?string $cleanerName = null,
        ?float $latitude = null,
        ?float $longitude = null,
    ): void {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return; // silently skip if file not found or unreadable
        }
        
        try {
            $manager = new ImageManager(new Driver());
            $image   = $manager->read($absolutePath);

            // Compress massive mobile photos down to 1080p equivalent
            $image->scaleDown(1920, 1920);

            // Save with reasonable quality to reduce footprint
            $image->save($absolutePath, 75);
        } catch (Throwable $e) {
            // Avoid breaking uploads; log for later inspection
            report($e);
        }
    }

    /**
     * Calculate a readable font size proportional to image dimensions.
     *
     * The goal is text that's clearly readable at normal viewing size
     * without covering excessive portions of the image.
     */
    private static function calculateFontSize(int $imageWidth): int
    {
        if ($imageWidth >= 1600) {
            return 72;
        }

        if ($imageWidth >= 1200) {
            return 60;
        }

        if ($imageWidth >= 800) {
            return 48;
        }

        if ($imageWidth >= 400) {
            return 36;
        }

        return 24;
    }

    /**
     * Resolve the path to the bundled TTF font.
     */
    private static function getFontPath(): ?string
    {
        $path = resource_path('fonts/Inter-Regular.ttf');

        return is_file($path) ? $path : null;
    }
}
