<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ConflictsDetected;
use App\Http\Controllers\Controller;
use App\Models\CleaningSession;
use App\Models\Property;
use App\Services\ActivityLogService;
use App\Services\CleaningJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class CalendarAssignController extends Controller
{
    public function store(Request $request, CleaningJobService $cleaningJobService): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'scheduled_date' => ['required', 'date'],
            'housekeeper_id' => ['required', 'integer', 'exists:users,id'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'session_id' => ['nullable', 'integer', 'exists:cleaning_sessions,id'],
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'completed'])],
            'sporadic_tasks' => ['nullable', 'array'],
            'sporadic_tasks.*' => ['string'],
            'confirm_conflicts' => ['nullable', 'boolean'],
        ]);

        $actor = $request->user();

        abort_unless(
            Property::query()->visibleTo($actor)->whereKey($data['property_id'])->exists(),
            403
        );

        $session = null;
        if (! empty($data['session_id'])) {
            $session = CleaningSession::query()->whereKey($data['session_id'])->first();
            abort_unless($session, 404, 'That cleaning job no longer exists.');
        }

        if (! $session && ! empty($data['booking_id'])) {
            $session = CleaningSession::where('booking_id', $data['booking_id'])
                ->where('property_id', $data['property_id'])
                ->where('status', '<>', 'cancelled')
                ->latest('id')->first();
        }

        $confirmed = (bool) ($data['confirm_conflicts'] ?? false);
        $overridden = $confirmed ? $cleaningJobService->detectConflicts($data, $session) : [];

        try {
            $saved = $cleaningJobService->quickAssign($actor, $data, $session, $confirmed);
        } catch (ConflictsDetected $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'needs_confirmation' => true,
                'conflicts' => $e->conflicts(),
            ], 409);
        } catch (ValidationException|HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'The cleaner could not be assigned. Please try again, or check that the property has an owner and tasks.',
            ], 500);
        }

        ActivityLogService::admin(
            'cleaning_job_calendar_assigned',
            'Cleaner assigned from the calendar.',
            'cleaning',
            [
                'property_id' => $saved->property_id,
                'subject_type' => CleaningSession::class,
                'subject_id' => $saved->id,
                'severity' => 'success',
                'new_values' => [
                    'conflicts_overridden' => array_column($overridden, 'message'),
                    'housekeeper_id' => $saved->housekeeper_id,
                    'scheduled_date' => $saved->scheduled_date->toDateString(),
                    'scheduled_time' => $saved->scheduled_time?->toDateTimeString(),
                ],
            ]
        );

        return response()->json(['ok' => true]);
    }

    /** Admin override: confirm an assignment when the cleaner can't reply by text. */
    public function confirm(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'owner', 'company']), 403);
        $data = $request->validate(['session_id' => ['required', 'integer', 'exists:cleaning_sessions,id']]);
        $session = CleaningSession::findOrFail($data['session_id']);
        abort_unless(
            Property::query()->visibleTo($request->user())->whereKey($session->property_id)->exists(),
            403
        );
        if ($session->assignment_status !== 'pending_confirmation' || ! $session->housekeeper_id) {
            return response()->json(['message' => 'This job is not waiting for a reply.'], 422);
        }

        $session->forceFill(['assignment_status' => 'confirmed'])->saveQuietly();

        ActivityLogService::admin(
            'cleaning_job_confirmed_manually',
            'Assignment confirmed manually (cleaner did not reply by text).',
            'cleaning',
            [
                'property_id' => $session->property_id,
                'subject_type' => CleaningSession::class,
                'subject_id' => $session->id,
                'severity' => 'warning',
                'new_values' => ['housekeeper_id' => $session->housekeeper_id, 'assignment_status' => 'confirmed'],
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Everything the unified scheduling drawer needs. Ways in:
     *  - session_id: edit an existing cleaning job
     *  - booking_id: the cleaning for one booking's checkout (property is locked)
     *  - property_id (+ date): a new job, or fresh context after the property or date changed
     */
    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasAnyRole(['admin', 'owner', 'company']), 403);

        $session = null;
        $booking = null;
        $propertyLocked = false;

        if ($sessionId = (int) $request->query('session_id')) {
            $session = CleaningSession::with(['housekeeper', 'booking'])->findOrFail($sessionId);
            $booking = $session->booking;
        } elseif ($bookingId = (int) $request->query('booking_id')) {
            $booking = \App\Models\Booking::with('property')->findOrFail($bookingId);
            $session = CleaningSession::query()->where('booking_id', $booking->id)->latest('id')->first();
            $propertyLocked = true;
        }

        $propertyId = $session?->property_id ?? $booking?->property_id ?? ((int) $request->query('property_id') ?: null);
        if ($propertyId) {
            abort_unless(Property::query()->visibleTo($user)->whereKey($propertyId)->exists(), 403);
        }
        $property = $propertyId ? Property::find($propertyId) : null;

        $queryDate = (string) $request->query('date');
        $queryDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $queryDate) ? $queryDate : null;
        $date = $session?->scheduled_date?->toDateString()
            ?? $booking?->check_out_date?->toDateString()
            ?? $queryDate;

        // The guest this cleaning follows: the linked booking, else the guest checking out that day.
        $guest = $booking;
        if (! $guest && ! $session && $propertyId && $date) {
            $dead = ['cancelled', 'canceled', 'declined', 'rejected', 'expired', 'no_show'];
            $guest = \App\Models\Booking::query()
                ->where('property_id', $propertyId)
                ->whereDate('check_out_date', $date)
                ->whereNull('cancelled_at')
                ->where(fn ($w) => $w->whereNull('status')->orWhereNotIn('status', $dead))
                ->first();
        }

        $time = $session?->scheduled_time?->format('H:i');
        if (! $time) {
            if ($guest) {
                $time = \Carbon\Carbon::parse($guest->effectiveCheckoutTime())->format('H:i');
            } elseif (! $session) {
                $time = '10:00';
            }
        }

        $cleaners = collect();
        if ($propertyId) {
            $cleaners = \App\Models\User::query()->active()
                ->whereIn('id', \Illuminate\Support\Facades\DB::table('property_user')->where('property_id', $propertyId)->pluck('user_id'))
                ->orderBy('name')->get()
                ->filter(fn ($u) => $u->hasAnyRole(['housekeeper']))
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'has_phone' => (bool) ($u->phone_number ?: $u->phone)])->values();
        }

        return response()->json([
            'properties' => Property::query()->visibleTo($user)->orderBy('name')->get(['id', 'name', 'owner_id'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'has_owner' => (bool) $p->owner_id])->values(),
            'property_locked' => $propertyLocked,
            'property_id' => $propertyId,
            'property_name' => $property?->name,
            'guest' => $guest?->guest_name,
            'booking_id' => $guest?->id,
            'session_id' => $session?->id,
            'status' => $session?->status,
            'housekeeper_id' => $session?->housekeeper_id,
            'housekeeper_name' => $session?->housekeeper?->name,
            'assignment_status' => $session?->assignment_status,
            'sporadic_tasks' => $session?->sporadic_tasks ?? [],
            'date' => $date,
            'time' => $time,
            'cleaners' => $cleaners,
        ]);
    }
}
