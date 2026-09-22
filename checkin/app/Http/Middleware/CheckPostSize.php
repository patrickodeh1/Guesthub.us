<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPostSize
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('post') || $request->isMethod('put')) {
            $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
            $maxBytes = $this->iniToBytes(ini_get('post_max_size'));

            if ($maxBytes > 0 && $contentLength > $maxBytes) {
                $maxMb = round($maxBytes / 1048576, 1);

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => "The file you uploaded is too large. Please upload a file smaller than {$maxMb}MB.",
                    ], 413);
                }

                $response = $request->routeIs('guest.vehicle-info')
                    ? redirect()->route('guest.show', [
                        $request->route('booking_id'),
                        $request->route('token'),
                    ])
                    : back();

                return $response->withErrors([
                    'file' => "The file you uploaded is too large. Please upload a file smaller than {$maxMb}MB.",
                ]);
            }
        }

        return $next($request);
    }

    private function iniToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => (int) $value,
        };
    }
}
