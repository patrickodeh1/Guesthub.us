<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CleaningSession;
use App\Services\ActivityLogService;
use App\Services\CleaningJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class CalendarStatusController extends Controller
{
    public function store(Request $request, CleaningJobService $cleaningJobService): JsonResponse
    {
        $actor = $request->user();

        abort_unless($actor->hasAnyRole(['admin', 'owner', 'company', 'manager']), 403);

        $data = $request->validate([
            'session_id' => ['required', 'integer', 'exists:cleaning_sessions,id'],
            'status' => ['required', 'in:pending,in_progress,completed'],
        ]);

        $session = CleaningSession::query()->findOrFail($data['session_id']);
        $old = $session->status;

        try {
            $saved = $cleaningJobService->updateStatus($actor, $session, $data['status']);
        } catch (ValidationException|HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'The status could not be changed. Please try again.',
            ], 500);
        }

        $manual = $saved->status === 'completed' ? ' (marked manually from the calendar)' : ' from the calendar';

        ActivityLogService::admin(
            'cleaning_job_status_changed',
            'Cleaning status changed from ' . $old . ' to ' . $saved->status . $manual . '.',
            'cleaning',
            [
                'property_id' => $saved->property_id,
                'subject_type' => CleaningSession::class,
                'subject_id' => $saved->id,
                'severity' => 'info',
                'new_values' => ['status' => $saved->status],
            ]
        );

        return response()->json(['ok' => true, 'status' => $saved->status]);
    }
}
