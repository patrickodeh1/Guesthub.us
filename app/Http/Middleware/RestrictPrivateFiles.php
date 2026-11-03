<?php

namespace App\Http\Middleware;

use App\Models\CleaningSession;
use App\Models\Property;
use App\Models\RoomPhoto;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Files in the public disk are served by /file, /storage, /img and /og without login. That is fine for
 * property photos, brand images and guides, but not for cleaning evidence and access photos
 * (lockbox / door-code steps). Those folders now need a login and access to the session they belong to.
 */
class RestrictPrivateFiles
{
    /** Folders that embed the cleaning session id as their second path segment. */
    private const SESSION_FOLDERS = ['checklist-photos', 'checklist-videos', 'session-access-photos'];

    public function handle(Request $request, Closure $next): Response
    {
        $path = ltrim((string) $request->route('path'), '/');
        $path = preg_replace('#^(storage|public)/#', '', $path);

        abort_if(str_contains($path, '..'), 404);

        $first = strtok($path, '/');
        $sessionId = null;

        if (in_array($first, self::SESSION_FOLDERS, true)) {
            $sessionId = preg_match('#^[^/]+/(\d+)/#', $path, $m) ? (int) $m[1] : 0;
        } elseif ($first === 'room_photos') {
            $sessionId = RoomPhoto::query()->where('path', $path)->value('session_id') ?? 0;
            // A photo we cannot match to a session still needs a login (never public).
            if (! $sessionId) {
                abort_unless($request->user(), 403);

                return $next($request);
            }
        } else {
            return $next($request);   // everything else stays public
        }

        $user = $request->user();
        abort_unless($user, 403);

        if ($user->hasRole('admin')) {
            return $next($request);
        }

        $session = $sessionId ? CleaningSession::query()->find($sessionId) : null;
        abort_unless($session, 404);

        $ok = (int) $session->housekeeper_id === (int) $user->id
            || ($user->hasAnyRole(['owner', 'company', 'manager'])
                && Property::query()->visibleTo($user)->whereKey($session->property_id)->exists());
        abort_unless($ok, 403);

        return $next($request);
    }
}
