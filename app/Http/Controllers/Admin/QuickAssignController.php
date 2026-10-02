<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CleaningSession;
use App\Services\ActivityLogService;
use App\Services\CheckoutOverviewService;
use App\Services\CleaningJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickAssignController extends Controller
{
    public function store(
        Request $request,
        CleaningJobService $cleaningJobService,
        CheckoutOverviewService $checkoutOverviewService
    ): JsonResponse {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'scheduled_date' => ['required', 'date'],
            'housekeeper_id' => ['required', 'integer', 'exists:users,id'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'session_id' => ['nullable', 'integer', 'exists:cleaning_sessions,id'],
        ]);

        $actor = $request->user();
        $board = $checkoutOverviewService->get($actor);
        $row = collect($board['groups']['today'])
            ->concat($board['groups']['tomorrow'])
            ->concat($board['groups']['later'])
            ->first(fn (array $item) => $item['property']?->id === (int) $data['property_id']
                && $item['date']->toDateString() === $data['scheduled_date']);

        abort_unless($row, 404, 'The checkout is no longer on the dashboard.');
        abort_unless(
            ($row['session']?->id ?? null) === (isset($data['session_id']) ? (int) $data['session_id'] : null),
            409,
            'The cleaning job changed. Refresh the dashboard and try again.'
        );

        try {
            $session = $cleaningJobService->quickAssign($actor, $data, $row['session']);
        } catch (\Illuminate\Validation\ValidationException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'The cleaner could not be assigned. Please try again, or check that the property has an owner and tasks.',
            ], 500);
        }

        ActivityLogService::admin(
            'cleaning_job_quick_assigned',
            'Cleaner assigned from the dashboard checkout board.',
            'cleaning',
            [
                'property_id' => $session->property_id,
                'subject_type' => CleaningSession::class,
                'subject_id' => $session->id,
                'severity' => 'success',
                'new_values' => [
                    'housekeeper_id' => $session->housekeeper_id,
                    'scheduled_date' => $session->scheduled_date->toDateString(),
                    'scheduled_time' => $session->scheduled_time?->toDateTimeString(),
                ],
            ]
        );

        $updatedBoard = $checkoutOverviewService->get($actor);
        $updatedRow = collect($updatedBoard['groups']['today'])
            ->concat($updatedBoard['groups']['tomorrow'])
            ->concat($updatedBoard['groups']['later'])
            ->first(fn (array $item) => $item['property']?->id === (int) $data['property_id']
                && $item['date']->toDateString() === $data['scheduled_date']);

        abort_unless($updatedRow, 500, 'The cleaning job was saved, but the dashboard row could not be refreshed.');

        return response()->json([
            'row_html' => view('dashboard-checkout-row', ['row' => $updatedRow])->render(),
            'coverage_html' => view('dashboard-checkout-coverage', [
                'summary' => $updatedBoard['summary'],
            ])->render(),
            'summary' => $updatedBoard['summary'],
        ]);
    }
}
