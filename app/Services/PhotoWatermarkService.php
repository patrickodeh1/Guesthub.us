<?php
namespace App\Services;

use App\Models\CleaningSession;
use App\Helpers\TimezoneHelper;
use Illuminate\Support\Facades\Log;

class PhotoWatermarkService
{
    /**
     * Embed property/timestamp/cleaner/GPS text permanently into image pixels.
     *
     * Returns watermarked JPEG content on success, or original content on failure.
     */
    public static function applyWatermark(
        string $imageContent, 
        CleaningSession $session, 
        string $photoType, // 'verify' or 'finished'
        ?string $roomName = null, 
        $capturedAt = null, 
        ?string $taskName = null
    ): string {
        try {
            if (!function_exists('imagecreatefromstring')) {
                Log::warning('PhotoWatermark: GD extension not available');
                return $imageContent;
            }

            $img = @imagecreatefromstring($imageContent);
            if ($img === false) {
                Log::warning('PhotoWatermark: imagecreatefromstring failed - unsupported image format');
                return $imageContent;
            }

            imagealphablending($img, true);
            imagesavealpha($img, true);

            $width  = imagesx($img);
            $height = imagesy($img);

            $propName = $session->property->name ?? 'Property';

            // Resolve Font
            $font = resource_path('fonts/arial.ttf');
            if (!file_exists($font)) {
                $font = resource_path('fonts/Inter-Regular.ttf');
            }
            $useTTF = file_exists($font);

            // Determine text to render based on explicit photoType
            $lines = [];
            
            if ($photoType === 'verify') {
                // VERIFY PHOTO (Compliance)
                // Embedded text MUST be: Property Name — Task Name ONLY
                $lines[] = $propName . ' — ' . ($taskName ?? 'Verify Task');
            } else {
                // FINISHED PHOTO (Room)
                $cleanerName = $session->housekeeper?->name ?? 'Unassigned';

                $tz = $session->property->timezone ?? config('app.timezone');
                $localCapturedAt = $capturedAt
                    ? TimezoneHelper::toLocal($capturedAt, $tz)
                    : null;
                $timestampStr = $localCapturedAt
                    ? $localCapturedAt->format('g:i A, F j, Y')
                    : 'No timestamp';

                $lat = $session->start_latitude;
                $lng = $session->start_longitude;
                $gpsStr = '';
                if ($lat && $lng) {
                    $latDir = $lat >= 0 ? 'N' : 'S';
                    $lngDir = $lng >= 0 ? 'E' : 'W';
                    $gpsStr = ', ' . number_format(abs($lat), 5) . chr(194).chr(176) . $latDir . ', ' . number_format(abs($lng), 5) . chr(194).chr(176) . $lngDir;
                }

                $lines = [
                    $propName . ' - ' . ($roomName ?? 'Room'),
                    'Captured: ' . $timestampStr,
                    'Cleaner: ' . $cleanerName,
                ];
                if ($gpsStr !== '') {
                    $lines[] = 'GPS: ' . ltrim($gpsStr, ', ');
                }
            }

            $baseFontSize = max(14, $width * 0.025);
            $paddingLeft = max(10, $width * 0.02);
            $paddingBottom = max(10, $height * 0.02);

            if ($useTTF) {
                $maxWidth = $width - ($paddingLeft * 2);
                $fits = false;
                while ($baseFontSize > 3 && !$fits) {
                    $fits = true;
                    foreach ($lines as $line) {
                        $bbox = imagettfbbox($baseFontSize, 0, $font, $line);
                        $textWidth = abs($bbox[4] - $bbox[0]); // Lower right X - Lower left X
                        if ($textWidth > $maxWidth) {
                            $fits = false;
                            $baseFontSize--;
                            break;
                        }
                    }
                }
            } else {
                Log::warning('PhotoWatermark: TTF font not found, falling back to GD built-in font');
            }

            $lineSpacing = $baseFontSize * 1.5;
            
            $totalTextHeight = count($lines) * $lineSpacing;
            $startY = $height - $paddingBottom - $totalTextHeight + $baseFontSize;

            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 0, 0, 0);

            // Hide the historical burned-in metadata ONLY for verify photos
            if ($photoType === 'verify') {
                // Calculate overlay size based on the ORIGINAL base font size, not the shrunk one,
                // because the historical text we are trying to cover was drawn large.
                $originalFontSize = max(14, $width * 0.025);
                $originalLineSpacing = $originalFontSize * 1.5;
                $overlayHeight = (4 * $originalLineSpacing) + ($paddingBottom * 3);
                $overlayY = $height - $overlayHeight;
                $overlayColor = imagecolorallocate($img, 15, 15, 15); // Solid very dark grey/black
                imagefilledrectangle($img, 0, (int)$overlayY, (int)($width), $height, $overlayColor);
            }
            
            $y = $startY;
            
            foreach ($lines as $i => $text) {
                if ($useTTF) {
                    $shadowOffset = max(1, (int)($baseFontSize * 0.08)); 
                    
                    for ($dx = -$shadowOffset; $dx <= $shadowOffset; $dx++) {
                        for ($dy = -$shadowOffset; $dy <= $shadowOffset; $dy++) {
                            if ($dx == 0 && $dy == 0) continue;
                            imagettftext($img, $baseFontSize, 0, (int)($paddingLeft + $dx), (int)($y + $dy), $black, $font, $text);
                        }
                    }
                    imagettftext($img, $baseFontSize, 0, (int)($paddingLeft + $shadowOffset + 1), (int)($y + $shadowOffset + 1), $black, $font, $text);
                    imagettftext($img, $baseFontSize, 0, (int)$paddingLeft, (int)$y, $white, $font, $text);
                } else {
                    imagestring($img, 5, (int)($paddingLeft + 1), (int)($y - $baseFontSize + 1), $text, $black);
                    imagestring($img, 5, (int)$paddingLeft, (int)($y - $baseFontSize), $text, $white);
                }
                $y += $lineSpacing;
            }

            ob_start();
            imagejpeg($img, null, 92);
            $watermarked = ob_get_clean();
            imagedestroy($img);

            if (empty($watermarked)) {
                return $imageContent;
            }

            return $watermarked;

        } catch (\Throwable $e) {
            Log::error('PhotoWatermark: Exception - ' . $e->getMessage());
            return $imageContent;
        }
    }
}
