<?php

namespace App\Http\Controllers\Admin;

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

        try {
            $saved = $cleaningJobService->quickAssign($actor, $data, $session);
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
                    'housekeeper_id' => $saved->housekeeper_id,
                    'scheduled_date' => $saved->scheduled_date->toDateString(),
                    'scheduled_time' => $saved->scheduled_time?->toDateTimeString(),
                ],
            ]
        );

        return response()->json(['ok' => true]);
    }
}
