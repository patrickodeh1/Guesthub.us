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
            'confirm_conflicts' => ['nullable', 'boolean'],
        ]);

        $actor = $request->user();

        abort_unless(
            Property::query()->visibleTo($actor)->whereKey($data['property_id'])->exists(),
            403
        );

        $session = null;
        if (! empty($data['session_id'])) {
            $session = CleaningSession::query()
                ->where('property_id', $data['property_id'])
                ->whereKey($data['session_id'])
                ->first();
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

    /** Data the guest-list / booking-page assign drawer needs for one booking. */
    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'owner', 'company']), 403);
        $bookingId = (int) $request->query('booking_id');
        $booking = \App\Models\Booking::with('property')->findOrFail($bookingId);
        abort_unless(
            Property::query()->visibleTo($request->user())->whereKey($booking->property_id)->exists(),
            403
        );

        $session = CleaningSession::query()->where('booking_id', $booking->id)->latest('id')->first();
        $time = $session?->scheduled_time?->format('H:i')
            ?? \Carbon\Carbon::parse($booking->effectiveCheckoutTime())->format('H:i');
        $cleaners = \App\Models\User::query()->active()
            ->whereIn('id', \Illuminate\Support\Facades\DB::table('property_user')->where('property_id', $booking->property_id)->pluck('user_id'))
            ->orderBy('name')->get()
            ->filter(fn ($u) => $u->hasAnyRole(['housekeeper']))
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'has_phone' => (bool) ($u->phone_number ?: $u->phone)])->values();

        return response()->json([
            'property_id' => $booking->property_id,
            'property_name' => $booking->property?->name,
            'guest' => $booking->guest_name,
            'session_id' => $session?->id,
            'housekeeper_id' => $session?->housekeeper_id,
            'housekeeper_name' => $session?->housekeeper?->name,
            'assignment_status' => $session?->assignment_status,
            'date' => ($session?->scheduled_date ?? $booking->check_out_date)->toDateString(),
            'time' => $time,
            'cleaners' => $cleaners,
        ]);
    }
}
