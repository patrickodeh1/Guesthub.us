<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class Branding
{
    public const DEFAULT_THEME_COLOR = '#082b49';

    public const DEFAULT_BUTTON_COLOR = '#0b2d4d';

    public static function themeColor(): string
    {
        return self::validHex(Setting::getValue('theme_color', self::DEFAULT_THEME_COLOR), self::DEFAULT_THEME_COLOR);
    }

    public static function buttonColor(string $variant = 'primary'): string
    {
        $variant = strtolower($variant);
        $fallback = $variant === 'primary' ? self::DEFAULT_BUTTON_COLOR : match ($variant) {
            'success' => '#10b981',
            'danger' => '#ef4444',
            'warning' => '#f59e0b',
            'info' => '#06b6d4',
            default => self::themeColor(),
        };

        return self::validHex(Setting::getValue("button_{$variant}_color", $fallback), $fallback);
    }

    public static function contrastText(string $hex): string
    {
        $hex = ltrim(self::validHex($hex, self::DEFAULT_THEME_COLOR), '#');
        $channels = array_map(fn (string $channel): float => hexdec($channel) / 255, str_split($hex, 2));
        $linear = array_map(
            fn (float $channel): float => $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4,
            $channels,
        );
        $luminance = (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);

        return $luminance > 0.45 ? '#0f172a' : '#ffffff';
    }

    public static function siteName(): string
    {
        return Setting::getValue('site_name', config('app.name', 'Guest Hub'));
    }

    public static function logoUrl(): ?string
    {
        return self::assetUrl(Setting::get('application_logo_path') ?: Setting::getValue('site_logo'));
    }

    public static function iconUrl(): ?string
    {
        return self::assetUrl(Setting::get('application_icon_path')) ?? self::logoUrl();
    }

    public static function faviconUrl(): ?string
    {
        return self::assetUrl(Setting::get('favicon_path') ?: Setting::getValue('favicon'))
            ?? self::iconUrl();
    }

    private static function assetUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $path = ltrim($path, '/');

        if (Storage::disk('public')->exists($path)) {
            return url('file/'.$path);
        }

        if (is_file(public_path('img/'.$path))) {
            return url('img/'.$path);
        }

        if (is_file(public_path($path))) {
            return asset($path);
        }

        return null;
    }

    private static function validHex(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) ? $value : $fallback;
    }
}
