<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    protected string $baseUrl = 'https://api.open-meteo.com/v1/forecast';

    /**
     * WMO weather codes -> [label, icon key].
     * https://open-meteo.com/en/docs (weathercode table)
     */
    protected array $codeMap = [
        0 => ['Clear sky', 'sun'],
        1 => ['Mainly clear', 'sun'],
        2 => ['Partly cloudy', 'cloud-sun'],
        3 => ['Overcast', 'cloud'],
        45 => ['Fog', 'fog'],
        48 => ['Fog', 'fog'],
        51 => ['Light drizzle', 'drizzle'],
        53 => ['Drizzle', 'drizzle'],
        55 => ['Heavy drizzle', 'drizzle'],
        61 => ['Light rain', 'rain'],
        63 => ['Rain', 'rain'],
        65 => ['Heavy rain', 'rain'],
        71 => ['Light snow', 'snow'],
        73 => ['Snow', 'snow'],
        75 => ['Heavy snow', 'snow'],
        80 => ['Rain showers', 'rain'],
        81 => ['Rain showers', 'rain'],
        82 => ['Violent rain showers', 'rain'],
        95 => ['Thunderstorm', 'storm'],
        96 => ['Thunderstorm w/ hail', 'storm'],
        99 => ['Thunderstorm w/ hail', 'storm'],
    ];

    /**
     * Returns ['temperature' => float|null, 'unit' => 'F', 'condition' => string|null, 'icon' => string|null]
     * or null on failure. Cached 15 minutes per lat/long pair.
     */
    public function getCurrent(float $latitude, float $longitude): ?array
    {
        $cacheKey = 'weather:'.round($latitude, 3).':'.round($longitude, 3);

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($latitude, $longitude) {
            try {
                $response = Http::timeout(10)->get($this->baseUrl, [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => 'temperature_2m,weather_code',
                    'temperature_unit' => 'fahrenheit',
                ]);

                if (!$response->successful()) {
                    Log::warning('Open-Meteo request failed', ['status' => $response->status()]);
                    return null;
                }

                $temp = $response->json('current.temperature_2m');
                $code = $response->json('current.weather_code');

                if ($temp === null || $code === null) {
                    return null;
                }

                [$label, $icon] = $this->codeMap[$code] ?? ['Unknown', 'cloud'];

                return [
                    'temperature' => round($temp),
                    'unit' => 'F',
                    'condition' => $label,
                    'icon' => $icon,
                ];
            } catch (\Throwable $e) {
                Log::error('Open-Meteo request threw an exception', ['message' => $e->getMessage()]);
                return null;
            }
        });
    }
}
