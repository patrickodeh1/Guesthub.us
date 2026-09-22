<?php

namespace App\Http\Controllers;

use App\Helpers\TimezoneHelper;
use App\Models\ChecklistItem;
use App\Models\ChecklistItemPhoto;
use App\Models\CleaningSession;
use App\Models\Room;
use App\Services\PersistentPhotoStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class SessionReportController extends Controller
{
    public function show(string $token, Request $request): View
    {
        $session = CleaningSession::query()
            ->where('report_token', $token)
            ->with([
                'property.rooms' => fn($q) => $q->orderBy('property_room.sort_order'),
                'owner:id,name,email',
                'housekeeper:id,name,email',
                'checklistItems.task:id,name,type,phase',
                'checklistItems.user:id,name',
                'checklistItems.photos',
            ])
            ->firstOrFail();

        $session->ensureReportToken();

        if (!$session->property) {
            abort(404, 'The associated property for this report could not be found.');
        }

        $allRoomPhotos = $session->photos->whereNotNull('room_id');

        $viewMode = $request->query('view') === 'pdf' ? 'pdf' : 'web';
        $isComplete = $session->status === 'completed';
        
        $reportUrl = route('reports.sessions.show', ['token' => $session->report_token]);
        $webUrl = $reportUrl;
        $pdfUrl = $reportUrl . '?view=pdf';
        $photosZipUrl = route('reports.sessions.photos.zip', ['token' => $session->report_token]);

        $checklistItems = $this->deduplicateChecklistItems($session->checklistItems);
        $resolvePhotoUrl = fn(?string $pathOrUrl): ?string => $this->resolveReportPhotoUrl($session->report_token, $pathOrUrl);
        $resolvePhotoDownloadUrl = fn(?string $pathOrUrl): ?string => $this->resolveReportPhotoDownloadUrl($session->report_token, $pathOrUrl);
        $checklistItems = $this->withResolvedChecklistPhotoUrls($checklistItems, $resolvePhotoUrl);

        // Exclude instruction-only tasks from completion/compliance counts
        $actionableItems = $checklistItems->filter(fn($item) => !$item->isInstructionTask());
        $totalChecklist = $actionableItems->count();
        $checkedChecklist = $actionableItems->where('checked', true)->count();
        $completionRate = $totalChecklist > 0
            ? (int) round(($checkedChecklist / $totalChecklist) * 100)
            : 0;

        $roomIds = $checklistItems
            ->pluck('room_id')
            ->filter()
            ->unique()
            ->values();

        $roomModels = Room::query()
            ->whereIn('id', $roomIds)
            ->get()
            ->keyBy('id');

        $propertyRoomIds = $session->property->rooms->pluck('id')->values()->all();
        $roomOrder = array_flip($propertyRoomIds);

        $roomSections = $roomIds
            ->sortBy(fn($id) => $roomOrder[$id] ?? (10000 + (int) $id))
            ->values()
            ->map(function ($roomId) use ($checklistItems, $roomModels, $isComplete) {
                $items = $checklistItems
                    ->where('room_id', $roomId)
                    ->filter(fn($item) => !$item->isVerificationItem() && !$item->isInstructionTask())
                    ->sortBy(fn($item) => sprintf(
                        '%d-%s-%010d',
                        $this->taskTypeOrder($item->task?->type),
                        strtolower($item->task?->name ?? ''),
                        $item->id
                    ))
                    ->values();

                return [
                    'id' => $roomId,
                    'name' => $roomModels->get($roomId)?->name ?? 'Room #' . $roomId,
                    'total' => $items->count(),
                    'checked' => $items->where('checked', true)->count(),
                    'items' => $items,
                    'incomplete_items' => $isComplete ? $items->where('checked', false)->values() : collect(),
                    'completed_items' => $items->where('checked', true)->values(),
                ];
            })
            ->filter(fn($section) => $section['total'] > 0)
            ->values();

        $propertyItems = $checklistItems
            ->whereNull('room_id')
            ->filter(fn($item) => !$item->isVerificationItem() && !$item->isInstructionTask())
            ->sortBy(fn($item) => sprintf(
                '%d-%s-%010d',
                $this->phaseOrder($item->task?->phase),
                strtolower($item->task?->name ?? ''),
                $item->id
            ))
            ->values();

        $propertyIncompleteItems = $isComplete ? $propertyItems->where('checked', false)->values() : collect();
        $propertyCompletedItems = $propertyItems->where('checked', true)->values();

        $roomNameMap = $roomSections->pluck('name', 'id');
        $defaultUploader = $session->housekeeper?->name ?? 'Housekeeper';

        $allRoomPhotos = $session->photos->map(function ($photo) use ($roomNameMap, $resolvePhotoUrl, $resolvePhotoDownloadUrl, $defaultUploader) {
            $photoPath = $photo->path ?? $photo->url ?? null;
            $url = $resolvePhotoUrl($photoPath);
            if ($url === null) {
                return null;
            }

            return [
                'room_id' => $photo->room_id,
                'room_name' => $roomNameMap[$photo->room_id] ?? ('Room #' . $photo->room_id),
                'url' => $url,
                'path' => $photo->path,
                'download_url' => $resolvePhotoDownloadUrl($photo->path ?? null),
                'captured_at' => $photo->captured_at,
                'caption' => 'Room photo',
                'note' => null,
                'uploader' => $defaultUploader,
                'source' => 'summary',
            ];
        })->filter()->values();

        $allTaskPhotos = $checklistItems
            ->flatMap(function ($item) use ($roomNameMap, $resolvePhotoDownloadUrl, $defaultUploader) {
                return $item->photos->map(function ($photo) use ($item, $roomNameMap, $resolvePhotoDownloadUrl, $defaultUploader) {
                    $url = (string) ($photo->report_url ?? '');
                    if ($url === '') {
                        return null;
                    }

                    return [
                        'room_id' => $item->room_id,
                        'room_name' => $item->room_id
                            ? ($roomNameMap[$item->room_id] ?? ('Room #' . $item->room_id))
                            : 'Property-level',
                        'url' => $url,
                        'path' => $photo->path,
                        'download_url' => $resolvePhotoDownloadUrl($photo->path ?? null),
                        'captured_at' => $photo->captured_at,
                        'caption' => $item->task?->name ?: 'Task photo',
                        'note' => $photo->note,
                        'uploader' => $item->user?->name ?? $defaultUploader,
                        'source' => 'task',
                    ];
                });
            })
            ->filter()
            ->unique(fn($photo) => $photo['url'] . '|' . ($photo['captured_at']?->timestamp ?? 0))
            ->values();

        $propertyRoomNames = $session->property->rooms->pluck('name', 'id');
        $completionRoomIds = $allRoomPhotos
            ->pluck('room_id')
            ->filter()
            ->unique()
            ->sortBy(fn($roomId) => $roomOrder[$roomId] ?? (10000 + (int) $roomId))
            ->values();

        $completionGalleryByRoom = $completionRoomIds->map(function ($roomId) use ($allRoomPhotos, $propertyRoomNames, $session) {
            $photos = $allRoomPhotos
                ->where('room_id', $roomId)
                ->sortBy(fn($photo) => $photo['captured_at']?->timestamp ?? PHP_INT_MAX)
                ->values();

            return [
                'room_id' => $roomId,
                'room_name' => $propertyRoomNames[$roomId] ?? ('Room #' . $roomId),
                'photos' => $photos,
                'photo_count' => $photos->count(),
                'download_url' => route('reports.sessions.photos.zip', [
                    'token' => $session->report_token,
                    'room_id' => $roomId,
                ]),
            ];
        });

        $allCompletionPhotos = $completionGalleryByRoom
            ->flatMap(fn($roomGallery) => $roomGallery['photos'])
            ->values();

        $propertyGallery = $allTaskPhotos
            ->whereNull('room_id')
            ->sortBy(fn($photo) => $photo['captured_at']?->timestamp ?? PHP_INT_MAX)
            ->values();

        $exceptionRows = $this->buildExceptionRows($checklistItems, $roomNameMap, $session->report_token, $defaultUploader, $isComplete);

        $issuesCount = $exceptionRows->count();
        $urgentIssues = $exceptionRows->where('priority', 'Urgent')->count();
        $normalIssues = $exceptionRows->where('priority', 'Normal')->count();
        $fyiIssues = $exceptionRows->where('priority', 'FYI')->count();
        $newIssues = $exceptionRows->where('status', 'New')->count();

        $overallStatusCode = $this->overallStatusCode($session);

        if (!$isComplete) {
            $overallStatusCode = 'in_progress';
            $overallStatusText = 'In Progress';
        } else {
            $overallStatusText = match ($overallStatusCode) {
                'ready' => 'Ready',
                'ready_with_exceptions' => 'Ready with Exceptions',
                default => 'Not Ready',
            };
        }

        $isReady = $overallStatusCode !== 'not_ready' && $overallStatusCode !== 'in_progress';
        $statusText = $overallStatusText;

        $completionPhotosCount = $allCompletionPhotos->count();
        $totalPhotos = $completionPhotosCount + (int) $exceptionRows->sum('photo_count');
        $roomPhotosCount = $completionPhotosCount;
        $taskPhotosCount = $allTaskPhotos->count();
        $issuePhotoCount = (int) $exceptionRows->sum('photo_count');

        $suppliesSummary = $this->buildSuppliesSummary($checklistItems, $roomNameMap);

        $firstPhotoAt = $this->minDate(
            $allRoomPhotos
                ->pluck('captured_at')
                ->filter()
        );
        $lastRoomPhotoAt = $this->maxDate(
            $allRoomPhotos
                ->pluck('captured_at')
                ->filter()
        );
        $lastTaskPhotoAt = $this->maxDate(
            $allTaskPhotos
                ->pluck('captured_at')
                ->filter()
        );
        $lastChecklistAt = $this->maxDate($checklistItems->pluck('checked_at')->filter());
        $lastUpdatedAt = $this->maxDate($checklistItems->pluck('updated_at')->filter());
        $latestIssueAt = $this->maxDate($exceptionRows->pluck('logged_at')->filter());

        $endedAtForDisplay = $session->ended_at;
        if (!$endedAtForDisplay && $session->status === 'completed') {
            $endedAtForDisplay = $this->maxDate(collect([
                $lastTaskPhotoAt,
                $lastRoomPhotoAt,
                $lastChecklistAt,
                $latestIssueAt,
                $lastUpdatedAt,
                $session->updated_at,
                $session->started_at,
            ])->filter());
        }

        $editedItems = $checklistItems
            ->filter(fn($item) => $item->updated_at && $item->created_at && $item->updated_at->gt($item->created_at))
            ->count();

        $timeline = collect([
            [
                'at' => $session->created_at,
                'label' => 'Work order created',
            ],
            [
                'at' => $session->gps_confirmed_at ?? $session->started_at,
                'label' => 'Session started by ' . ($session->housekeeper?->name ?? 'housekeeper'),
            ],
            [
                'at' => $firstPhotoAt,
                'label' => 'First timestamped photo uploaded',
            ],
            [
                'at' => $lastChecklistAt,
                'label' => "Checklist progress: {$checkedChecklist}/{$totalChecklist} completed",
            ],
            [
                'at' => $latestIssueAt,
                'label' => $issuesCount > 0 ? "Issues logged: {$issuesCount}" : null,
            ],
            [
                'at' => $lastUpdatedAt,
                'label' => $editedItems > 0 ? "Checklist edits made: {$editedItems}" : null,
            ],
            [
                'at' => $session->status === 'completed' ? $endedAtForDisplay : null,
                'label' => 'Session marked completed',
            ],
        ])->filter(fn($event) => !empty($event['at']) && !empty($event['label']))
            ->sortBy(fn($event) => $event['at']->timestamp)
            ->values();

        $workOrder = sprintf(
            'WO-%s-%04d',
            $session->scheduled_date?->format('Ymd') ?? now()->format('Ymd'),
            $session->id
        );

        $checkoutAt = $this->resolveCheckoutAt($session);
        $readyByAt = $endedAtForDisplay ?? $this->resolveReadyByAt($session);

        $hasPhotoZip = $completionPhotosCount > 0;

        $filter = new \App\Services\ReportItemFilter();
        $issues = $filter->getIssues($session);
        $complianceItems = $filter->getComplianceItems($session);

        // Convert all display timestamps to property-local timezone
        $propertyTz = $session->property->timezone ?? config('app.timezone');
        $localNow = TimezoneHelper::toLocal(now(), $propertyTz);
        $localCheckoutAt = TimezoneHelper::toLocal($checkoutAt, $propertyTz);
        $localReadyByAt = TimezoneHelper::toLocal($readyByAt, $propertyTz);
        $localEndedAt = TimezoneHelper::toLocal($endedAtForDisplay, $propertyTz);
        $actualStart = $session->gps_confirmed_at ?? $session->started_at;
        $localStartedAt = $actualStart ? TimezoneHelper::toLocal($actualStart, $propertyTz) : null;
        $cleaningDate = TimezoneHelper::toLocal($session->scheduled_date, $propertyTz);

        // Convert timeline events to property-local time
        $timeline = $timeline->map(function ($event) use ($propertyTz) {
            $event['at'] = TimezoneHelper::toLocal($event['at'], $propertyTz);
            return $event;
        })->filter(fn($event) => !empty($event['at']) && !empty($event['label']))->values();

        return view('reports.session', [
            'session' => $session,
            'viewMode' => $viewMode,
            'workOrder' => $workOrder,
            'reportUrl' => $reportUrl,
            'webUrl' => $webUrl,
            'pdfUrl' => $pdfUrl,
            'photosZipUrl' => $photosZipUrl,
            'hasPhotoZip' => $hasPhotoZip,
            'generatedAt' => $localNow,
            'checkoutAt' => $localCheckoutAt,
            'readyByAt' => $localReadyByAt,
            'startedAtForDisplay' => $localStartedAt,
            'endedAtForDisplay' => $localEndedAt,
            'cleaningDate' => $cleaningDate,
            'preparedBy' => $session->housekeeper?->name ?? 'Unassigned',
            'teamName' => $session->owner?->name ?? config('app.name', 'Team'),
            'unitLabel' => $this->extractUnitLabel($session->property->name),
            'durationMinutes' => $this->durationMinutes($session->started_at, $endedAtForDisplay),
            'durationLabel' => $this->formatDuration($this->durationMinutes($session->started_at, $endedAtForDisplay)),
            'totalChecklist' => $totalChecklist,
            'checkedChecklist' => $checkedChecklist,
            'uncheckedChecklist' => max(0, $totalChecklist - $checkedChecklist),
            'completionRate' => $completionRate,
            'isReady' => $isReady,
            'statusText' => $statusText,
            'overallStatusCode' => $overallStatusCode,
            'overallStatusText' => $overallStatusText,
            'issuesCount' => $issuesCount,
            'urgentIssues' => $urgentIssues,
            'normalIssues' => $normalIssues,
            'fyiIssues' => $fyiIssues,
            'newIssues' => $newIssues,
            'roomSections' => $roomSections,
            'propertyItems' => $propertyItems,
            'propertyIncompleteItems' => $propertyIncompleteItems,
            'propertyCompletedItems' => $propertyCompletedItems,
            'exceptionRows' => $exceptionRows,
            'issues' => $issues,
            'complianceItems' => $complianceItems,
            'completionGalleryByRoom' => $completionGalleryByRoom,
            'allCompletionPhotos' => $allCompletionPhotos,
            'propertyGallery' => $propertyGallery,
            'totalPhotos' => $totalPhotos,
            'completionPhotosCount' => $completionPhotosCount,
            'roomPhotosCount' => $roomPhotosCount,
            'taskPhotosCount' => $taskPhotosCount,
            'issuePhotoCount' => $issuePhotoCount,
            'suppliesSummary' => $suppliesSummary,
            'timeline' => $timeline,
            'isComplete' => $isComplete,
            'propertyTz' => $propertyTz,
        ]);
    }

    public function photo(string $token, string $path): Response
    {
        $session = CleaningSession::query()
            ->where('report_token', $token)
            ->firstOrFail();

        $normalizedPath = $this->normalizeStoragePath($path);
        abort_if($normalizedPath === null, 404);
        abort_unless($this->sessionHasPhotoPath($session, $normalizedPath), 404);
        $blob = PersistentPhotoStorage::read($normalizedPath);
        abort_if($blob === null, 404);

        return response($blob['content'], 200, [
            'Content-Type' => $blob['mime_type'] ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . basename($normalizedPath) . '"',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function photoDownload(string $token, string $path): StreamedResponse
    {
        $session = CleaningSession::query()
            ->where('report_token', $token)
            ->with([
                'photos',
                'checklistItems.photos',
                'property.rooms',
            ])
            ->firstOrFail();

        $normalizedPath = $this->normalizeStoragePath($path);
        abort_if($normalizedPath === null, 404);
        abort_unless($this->sessionHasPhotoPath($session, $normalizedPath), 404);
        $blob = PersistentPhotoStorage::read($normalizedPath);
        abort_if($blob === null, 404);

        $roomName = 'Property';
        $imageNumber = 1;
        $capturedAt = null;

        $matchedSessionPhoto = $session->photos->first(fn($p) => $this->normalizeStoragePath($p->path) === $normalizedPath);
        if ($matchedSessionPhoto) {
            $capturedAt = $matchedSessionPhoto->captured_at;
            $roomId = $matchedSessionPhoto->room_id;
            if ($roomId) {
                $room = $session->property->rooms->firstWhere('id', $roomId);
                $roomName = $room->name ?? 'Room';
            }
            $roomPhotos = $session->photos->where('room_id', $roomId)->values();
            $index = $roomPhotos->search(fn($p) => $p->id === $matchedSessionPhoto->id);
            $imageNumber = $index !== false ? $index + 1 : 1;
        } else {
            $allChecklistPhotos = $session->checklistItems->flatMap->photos;
            $matchedChecklistPhoto = $allChecklistPhotos->first(fn($p) => $this->normalizeStoragePath($p->path) === $normalizedPath);
            if ($matchedChecklistPhoto) {
                $capturedAt = $matchedChecklistPhoto->captured_at;
                $checklistItem = $session->checklistItems->firstWhere('id', $matchedChecklistPhoto->checklist_item_id);
                $roomId = $checklistItem ? $checklistItem->room_id : null;
                if ($roomId) {
                    $room = $session->property->rooms->firstWhere('id', $roomId);
                    $roomName = $room->name ?? 'Room';
                }
                $roomTaskPhotos = $allChecklistPhotos->filter(function($p) use ($session, $roomId) {
                    $item = $session->checklistItems->firstWhere('id', $p->checklist_item_id);
                    return $item && $item->room_id === $roomId;
                })->values();
                $index = $roomTaskPhotos->search(fn($p) => $p->id === $matchedChecklistPhoto->id);
                $imageNumber = $index !== false ? $index + 1 : 1;
            }
        }

        $extension = \App\Services\PhotoFilenameService::extensionFromPath($normalizedPath);
        $filename = \App\Services\PhotoFilenameService::generateFilename(
            roomName: $roomName,
            cleanerName: $session->housekeeper?->name ?? 'Unassigned',
            dateTime: $capturedAt ?? $session->scheduled_date,
            sequenceNumber: $imageNumber,
            extension: $extension,
        );

        return response()->streamDownload(function () use ($blob) {
            echo $blob['content'];
        }, $filename, [
            'Content-Type' => $blob['mime_type'] ?? 'application/octet-stream',
        ]);
    }

    public function photosZip(string $token, Request $request): BinaryFileResponse
    {
        $roomId = $request->integer('room_id');

        $session = CleaningSession::query()
            ->where('report_token', $token)
            ->with([
                'photos:id,session_id,room_id,path,captured_at',
                'property.rooms:id,name',
            ])
            ->firstOrFail();

        $roomNameById = $session->property->rooms->pluck('name', 'id');
        $roomPhotos = $session->photos;
        if (!empty($roomId)) {
            $roomPhotos = $roomPhotos->where('room_id', $roomId)->values();
        }

        $entries = $roomPhotos
            ->map(function ($photo) use ($roomNameById) {
                $path = $this->normalizeStoragePath($photo->path);
                if ($path === null || !PersistentPhotoStorage::exists($path)) {
                    return null;
                }

                return [
                    'path' => $path,
                    'room_id' => $photo->room_id,
                    'room_name' => $roomNameById[$photo->room_id] ?? ('Room-' . $photo->room_id),
                    'captured_at' => $photo->captured_at,
                ];
            })
            ->filter()
            ->values();

        abort_if($entries->isEmpty(), 404, 'No completion photos available for this report.');

        $tmpDir = storage_path('app/report-zips');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $zipPath = $tmpDir . DIRECTORY_SEPARATOR . sprintf(
            'session-%d-photos-%s.zip',
            $session->id,
            Str::lower(Str::random(8))
        );

        $zip = new ZipArchive();
        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        abort_unless($result === true, 500, 'Unable to create photo archive.');

        // Track sequence numbers per room for deterministic naming
        $roomSequences = [];
        $cleanerName = $session->housekeeper?->name ?? 'Unassigned';

        foreach ($entries as $entry) {
            $blob = PersistentPhotoStorage::read($entry['path']);
            if ($blob === null) {
                continue;
            }

            $entryRoomId = $entry['room_id'] ?? 0;
            $roomSequences[$entryRoomId] = ($roomSequences[$entryRoomId] ?? 0) + 1;
            $extension = \App\Services\PhotoFilenameService::extensionFromPath($entry['path']);

            $entryName = \App\Services\PhotoFilenameService::generateZipEntryName(
                roomName: $entry['room_name'],
                cleanerName: $cleanerName,
                dateTime: $entry['captured_at'] ?? $session->scheduled_date,
                sequenceNumber: $roomSequences[$entryRoomId],
                extension: $extension,
            );

            $zip->addFromString($entryName, $blob['content']);
        }

        $zip->close();

        $singleRoomName = (!empty($roomId) && $roomNameById->has($roomId))
            ? $roomNameById[$roomId]
            : null;

        $downloadName = \App\Services\PhotoFilenameService::generateZipFilename(
            propertyName: $session->property->name,
            cleanerName: $cleanerName,
            scheduledDate: $session->scheduled_date,
            roomName: $singleRoomName,
        );

        return response()
            ->download($zipPath, $downloadName, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    private function taskTypeOrder(?string $taskType): int
    {
        if ($taskType === 'inventory') {
            return 2;
        }

        return 1;
    }

    private function phaseOrder(?string $phase): int
    {
        return match ($phase) {
            'pre_cleaning' => 1,
            'during_cleaning' => 2,
            'post_cleaning' => 3,
            default => 4,
        };
    }

    private function durationMinutes(?Carbon $start, ?Carbon $end): ?int
    {
        if (!$start || !$end) {
            return null;
        }

        return (int) max(0, round($start->diffInMinutes($end)));
    }

    private function formatDuration(?int $minutes): string
    {
        if ($minutes === null) {
            return 'In progress';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours === 0) {
            return "{$mins}m";
        }

        return "{$hours}h {$mins}m";
    }

    private function minDate(Collection $dates): ?Carbon
    {
        if ($dates->isEmpty()) {
            return null;
        }

        return $dates->sortBy(fn($date) => $date->timestamp)->first();
    }

    private function maxDate(Collection $dates): ?Carbon
    {
        if ($dates->isEmpty()) {
            return null;
        }

        return $dates->sortByDesc(fn($date) => $date->timestamp)->first();
    }

    private function deduplicateChecklistItems(Collection $items): Collection
    {
        return $items
            ->groupBy(fn($item) => $this->checklistKey($item->room_id, $item->task_id))
            ->map(function (Collection $group) {
                /** @var ChecklistItem $base */
                $base = $group
                    ->sortByDesc(function ($item) {
                        return sprintf(
                            '%011d-%011d-%011d',
                            $item->updated_at?->timestamp ?? 0,
                            $item->checked_at?->timestamp ?? 0,
                            $item->id
                        );
                    })
                    ->first();

                $isChecked = $group->contains(fn($item) => (bool) $item->checked);
                $checkedAt = $group
                    ->where('checked', true)
                    ->pluck('checked_at')
                    ->filter()
                    ->sortByDesc(fn($date) => $date->timestamp)
                    ->first();

                $note = $group
                    ->sortByDesc(function ($item) {
                        return sprintf(
                            '%011d-%011d-%011d',
                            $item->updated_at?->timestamp ?? 0,
                            $item->checked_at?->timestamp ?? 0,
                            $item->id
                        );
                    })
                    ->pluck('note')
                    ->map(fn($value) => trim((string) $value))
                    ->first(fn($value) => $value !== '');

                $photos = $group
                    ->flatMap(fn($item) => $item->photos)
                    ->unique(fn($photo) => $photo->id ?? ($photo->path . '|' . ($photo->captured_at?->timestamp ?? 0)))
                    ->sortBy(fn($photo) => sprintf(
                        '%011d-%011d',
                        $photo->captured_at?->timestamp ?? PHP_INT_MAX,
                        $photo->id ?? 0
                    ))
                    ->values();

                $merged = clone $base;
                $merged->setAttribute('checked', $isChecked);
                $merged->setAttribute('checked_at', $isChecked ? $checkedAt : null);
                $merged->setAttribute('note', $note);
                $merged->setRelation('photos', $photos);

                return $merged;
            })
            ->values();
    }

    private function withResolvedChecklistPhotoUrls(Collection $checklistItems, callable $resolvePhotoUrl): Collection
    {
        return $checklistItems->map(function ($item) use ($resolvePhotoUrl) {
            $resolvedPhotos = $item->photos
                ->map(function ($photo) use ($resolvePhotoUrl) {
                    $url = $resolvePhotoUrl($photo->path ?? $photo->url ?? null);
                    if ($url === null) {
                        return null;
                    }

                    $clone = clone $photo;
                    $clone->setAttribute('report_url', $url);

                    return $clone;
                })
                ->filter()
                ->values();

            $item->setRelation('photos', $resolvedPhotos);

            return $item;
        });
    }

    private function buildExceptionRows(
        Collection $checklistItems,
        Collection $roomNameMap,
        string $reportToken,
        string $defaultUploader,
        bool $isComplete
    ): Collection {
        return $checklistItems
            // Exclude instruction-only tasks — they never generate issues
            ->filter(fn($item) => !$item->isInstructionTask())
            ->map(function ($item) {
                return [
                    'item' => $item,
                    'note' => trim((string) $item->note),
                ];
            })
            ->filter(fn($entry) => $this->isChecklistIssue($entry['item'], $entry['note'], $isComplete))
            ->map(function ($entry) use ($roomNameMap, $reportToken, $defaultUploader) {
                /** @var ChecklistItem $item */
                $item = $entry['item'];
                $note = $entry['note'];
                $location = $item->room_id
                    ? ($roomNameMap[$item->room_id] ?? ('Room #' . $item->room_id))
                    : 'Property-level';

                $photos = $item->photos
                    ->map(function ($photo) use ($reportToken, $defaultUploader) {
                        $url = $photo->report_url
                            ?? $this->resolveReportPhotoUrl($reportToken, $photo->path ?? $photo->url ?? null);

                        if ($url === null) {
                            return null;
                        }

                        return [
                            'url' => $url,
                            'download_url' => $this->resolveReportPhotoDownloadUrl($reportToken, $photo->path ?? null),
                            'captured_at' => $photo->captured_at,
                            'note' => trim((string) $photo->note),
                            'uploader' => $defaultUploader,
                        ];
                    })
                    ->filter()
                    ->values();

                $priority = $this->issuePriority($item, $note);
                $category = $this->issueCategory($item, $note);
                $status = $this->issueStatus($item, $note);
                $title = $item->task?->name ?? 'Task';
                $description = $note !== ''
                    ? Str::limit($note, 180)
                    : ($item->checked ? 'Noted during checklist review.' : 'This task is incomplete and needs follow-up.');

                $loggedAt = $this->maxDate($photos->pluck('captured_at')->filter())
                    ?? $item->checked_at
                    ?? $item->updated_at;

                return [
                    'priority' => $priority,
                    'category' => $category,
                    'location' => $location,
                    'title' => $title,
                    'description' => $description,
                    'full_note' => $note !== '' ? $note : $description,
                    'photo_count' => $photos->count(),
                    'thumbnails' => $photos->take(3)->values(),
                    'photos' => $photos,
                    'suggested_action' => $this->issueSuggestedAction($category, $priority),
                    'recommendation' => $this->issueRecommendation($category, $priority, $item->checked),
                    'status' => $status,
                    'logged_at' => $loggedAt,
                ];
            })
            ->sortBy(function ($issue) {
                $timeRank = 9999999999 - ($issue['logged_at']?->timestamp ?? 0);

                return sprintf(
                    '%d-%010d-%s',
                    $this->issuePriorityOrder($issue['priority']),
                    $timeRank,
                    strtolower($issue['title'])
                );
            })
            ->values();
    }

    private function issuePriority(ChecklistItem $item, string $note): string
    {
        $context = strtolower(($item->task?->name ?? '') . ' ' . $note);
        if ($this->containsAny($context, ['urgent', 'asap', 'immediate', 'safety', 'hazard', 'leak', 'mold', 'broken'])) {
            return 'Urgent';
        }

        if (!$item->checked) {
            return 'Normal';
        }

        return 'FYI';
    }

    /**
     * An item qualifies as an "issue" for the report if it has:
     * - A non-empty note (whitespace-only does not count)
     * - OR at least one photo attached
     *
     * Unchecked status alone does NOT make a task an issue.
     * Keyword detection is NOT used for issue determination.
     */
    private function isChecklistIssue(ChecklistItem $item, string $note, bool $isComplete): bool
    {
        $hasNote = $note !== '';
        
        if ($item->isVerificationItem()) {
            return $hasNote;
        }

        $hasPhotos = $item->photos->isNotEmpty();

        return $hasNote || $hasPhotos;
    }

    private function issueCategory(ChecklistItem $item, string $note): string
    {
        $context = strtolower(($item->task?->name ?? '') . ' ' . $note);

        if ($item->task?->type === 'inventory' || $this->containsAny($context, ['missing', 'out of stock', 'low stock', 'replace item', 'not available'])) {
            return 'Missing item';
        }

        if ($this->containsAny($context, ['leak', 'repair', 'maintenance', 'not working', 'electrical', 'hvac', 'plumbing'])) {
            return 'Maintenance';
        }

        if ($this->containsAny($context, ['damage', 'crack', 'broken', 'dent', 'tear', 'stain'])) {
            return 'Damage';
        }

        if ($this->containsAny($context, ['deep clean', 'heavy soil', 'buildup', 'grease', 'mildew'])) {
            return 'Deep clean needed';
        }

        return 'Maintenance';
    }

    /**
     * Determine the status of an issue.
     *
     * Does NOT use keyword detection — relies on structured ChecklistReport
     * resolution data when available.
     */
    private function issueStatus(ChecklistItem $item, string $note): string
    {
        if (!$item->checked) {
            return 'New';
        }

        return 'Acknowledged';
    }

    private function issueSuggestedAction(string $category, string $priority): string
    {
        if ($priority === 'Urgent') {
            return 'Immediate follow-up required';
        }

        return match ($category) {
            'Maintenance' => 'Maintenance visit needed',
            'Damage' => 'Assess and repair/replace damaged item',
            'Missing item' => 'Replace item before next check-in',
            'Deep clean needed' => 'Schedule deep clean pass',
            default => 'Review and schedule follow-up',
        };
    }

    private function issueRecommendation(string $category, string $priority, bool $isChecked): string
    {
        if ($priority === 'Urgent') {
            return 'Escalate now and confirm closure before guest arrival.';
        }

        if (!$isChecked) {
            return 'Complete the task and re-verify with updated photo evidence.';
        }

        return match ($category) {
            'Maintenance' => 'Patch if possible, otherwise create maintenance ticket.',
            'Damage' => 'Replace or repair and document completion photo.',
            'Missing item' => 'Restock item and verify count on next turnover.',
            'Deep clean needed' => 'Plan additional cleaning cycle and confirm results.',
            default => 'Monitor and review at the next service window.',
        };
    }

    private function issuePriorityOrder(string $priority): int
    {
        return match ($priority) {
            'Urgent' => 1,
            'Normal' => 2,
            default => 3,
        };
    }

    /**
     * Determine the overall session status using structured ChecklistReport data.
     *
     * - READY: Session completed, no unresolved formal exceptions.
     * - READY WITH EXCEPTIONS: Session completed, has unresolved formal
     *   maintenance/damage/missing-item exceptions (via ChecklistReport model).
     * - NOT READY: Session not completed.
     *
     * Does NOT use keyword detection on note text.
     * Does NOT treat every issue/note/photo as an automatic exception.
     */
    private function overallStatusCode(CleaningSession $session): string
    {
        if ($session->status !== 'completed') {
            return 'not_ready';
        }

        // Query formal exceptions from ChecklistReport that remain unresolved.
        // Only types representing operational problems count as exceptions.
        $unresolvedExceptions = \App\Models\ChecklistReport::where('session_id', $session->id)
            ->whereIn('type', ['damage', 'maintenance', 'supplies', 'safety'])
            ->where('status', '!=', 'resolved')
            ->exists();

        if ($unresolvedExceptions) {
            return 'ready_with_exceptions';
        }

        return 'ready';
    }

    private function buildSuppliesSummary(Collection $checklistItems, Collection $roomNameMap): array
    {
        $inventoryRows = $checklistItems
            ->filter(fn(ChecklistItem $item) => $this->isSupplyChecklistItem($item))
            ->map(fn(ChecklistItem $item) => $this->buildSupplyRow($item, $roomNameMap))
            ->groupBy(fn(array $row) => strtolower($row['name']) . '|' . $row['status_code'])
            ->map(function (Collection $group): array {
                $first = $group->first();
                $quantities = $group
                    ->pluck('quantity')
                    ->filter(fn($quantity) => $quantity !== null)
                    ->map(fn($quantity) => (int) $quantity);
                $locations = $group
                    ->pluck('location')
                    ->filter()
                    ->unique()
                    ->values();
                $notes = $group
                    ->pluck('note')
                    ->filter(fn($note) => trim((string) $note) !== '')
                    ->map(fn($note) => trim((string) $note))
                    ->unique()
                    ->values();

                return [
                    'name' => $first['name'],
                    'quantity' => $quantities->isNotEmpty() ? $quantities->sum() : null,
                    'status_code' => $first['status_code'],
                    'status' => $first['status'],
                    'location' => $locations->implode(', '),
                    'note' => $notes->take(2)->implode(' | '),
                    'sources' => $group->count(),
                ];
            })
            ->sortBy(fn(array $row) => sprintf(
                '%d-%s',
                $this->supplyStatusOrder($row['status_code']),
                strtolower($row['name'])
            ))
            ->values();

        $restockedItems = $inventoryRows
            ->where('status_code', 'restocked')
            ->values();

        $lowOrMissing = $inventoryRows
            ->filter(fn(array $row) => in_array($row['status_code'], ['low_stock', 'out_of_stock'], true))
            ->values();

        $ownerActionItems = $inventoryRows
            ->filter(fn(array $row) => in_array($row['status_code'], ['out_of_stock', 'low_stock', 'action_required'], true))
            ->map(function (array $row): array {
                $action = match ($row['status_code']) {
                    'out_of_stock' => 'Order ' . $row['name'],
                    'low_stock' => 'Restock ' . $row['name'],
                    default => 'Review ' . $row['name'],
                };

                return [
                    'name' => $row['name'],
                    'action' => $action,
                    'status_code' => $row['status_code'],
                    'status' => $row['status'],
                    'quantity' => $row['quantity'],
                    'location' => $row['location'],
                    'note' => $row['note'],
                ];
            })
            ->values();

        return [
            'used' => $inventoryRows->isNotEmpty(),
            'line_item_count' => $inventoryRows->count(),
            'restocked_items' => $restockedItems,
            'low_or_missing_items' => $lowOrMissing,
            'owner_action_items' => $ownerActionItems,
            'inventory_rows' => $inventoryRows,
            'restocked_count' => $restockedItems->count(),
            'low_or_missing_count' => $lowOrMissing->count(),
            'owner_action_count' => $ownerActionItems->count(),
        ];
    }

    private function isSupplyChecklistItem(ChecklistItem $item): bool
    {
        if ($item->task?->type === 'inventory') {
            return true;
        }

        $context = strtolower(trim(($item->task?->name ?? '') . ' ' . (string) $item->note));
        if ($context === '') {
            return false;
        }

        return $this->containsAny($context, [
            'restock',
            'refill',
            'supply',
            'inventory',
            'stock',
            'out of stock',
            'low stock',
            'paper towel',
            'toilet paper',
            'coffee pod',
            'dishwasher pod',
            'soap',
            'shampoo',
            'conditioner',
            'trash bag',
            'amenity',
            'linen',
            'towel',
            'detergent',
        ]);
    }

    private function buildSupplyRow(ChecklistItem $item, Collection $roomNameMap): array
    {
        $taskName = trim((string) ($item->task?->name ?? 'Supply item'));
        $note = trim((string) $item->note);
        $statusCode = $this->resolveSupplyStatusCode($item, $taskName, $note);

        return [
            'name' => $this->normalizeSupplyItemName($taskName, $note),
            'quantity' => $this->resolveSupplyQuantity($item, $taskName, $note, $statusCode),
            'status_code' => $statusCode,
            'status' => $this->supplyStatusLabel($statusCode),
            'location' => $item->room_id
                ? $roomNameMap->get($item->room_id, 'Room #' . $item->room_id)
                : 'Property-level',
            'note' => $note,
        ];
    }

    private function resolveSupplyStatusCode(ChecklistItem $item, string $taskName, string $note): string
    {
        $context = strtolower(trim($taskName . ' ' . $note));

        if ($this->containsAny($context, ['out of stock', 'no stock', 'missing', 'not available', 'unavailable', 'empty'])) {
            return 'out_of_stock';
        }

        if ($this->containsAny($context, ['low stock', 'running low', 'almost empty'])) {
            return 'low_stock';
        }

        if (!$item->checked || $this->containsAny($context, ['order', 'purchase', 'need to buy', 'owner action'])) {
            return 'action_required';
        }

        return 'restocked';
    }

    private function resolveSupplyQuantity(ChecklistItem $item, string $taskName, string $note, string $statusCode): ?int
    {
        if ($item->quantity !== null) {
            return $item->quantity;
        }

        $checked = (bool) $item->checked;
        if ($statusCode === 'out_of_stock') {
            return 0;
        }

        $quantity = $this->extractQuantity($note);
        if ($quantity === null) {
            $quantity = $this->extractQuantity($taskName);
        }

        if ($quantity !== null) {
            return $quantity;
        }

        if ($checked && $statusCode === 'restocked') {
            return 1;
        }

        return null;
    }

    private function normalizeSupplyItemName(string $taskName, string $note): string
    {
        $name = preg_replace(
            '/^(?:restock(?:ed)?|refill(?:ed)?|replace(?:d)?|check|inspect|verify|ensure|top[\s-]?up|inventory(?:\s+check)?|check\s+inventory)\s+/i',
            '',
            $taskName
        );
        $name = preg_replace('/\s*(?:inventory|stock|supplies?)$/i', '', (string) $name);
        $name = trim((string) $name, " \t\n\r\0\x0B-:.;");

        $isGeneric = in_array(strtolower($name), [
            '',
            'inventory',
            'check inventory',
            'item',
            'supply item',
            'supplies',
            'stock',
        ], true);

        if ($isGeneric) {
            $noteName = $this->extractSupplyNameFromNote($note);
            if ($noteName !== null) {
                $name = $noteName;
            } elseif (trim($taskName) !== '') {
                $name = $taskName;
            }
        }

        if ($name === '') {
            $name = 'Supply item';
        }

        return (string) Str::of($name)->squish()->title();
    }

    private function extractSupplyNameFromNote(string $note): ?string
    {
        $value = trim($note);
        if ($value === '') {
            return null;
        }

        $lower = strtolower($value);
        if (!$this->containsAny($lower, [
            'paper',
            'toilet',
            'coffee',
            'pod',
            'soap',
            'shampoo',
            'conditioner',
            'dishwasher',
            'detergent',
            'trash',
            'linen',
            'towel',
            'supply',
            'stock',
            'amenity',
        ])) {
            return null;
        }

        if (preg_match('/([a-z][a-z0-9\/\-\s]{2,40})/i', $value, $matches) !== 1) {
            return null;
        }

        $candidate = trim((string) ($matches[1] ?? ''), " \t\n\r\0\x0B-:.;");

        return $candidate !== '' ? $candidate : null;
    }

    private function supplyStatusLabel(string $statusCode): string
    {
        return match ($statusCode) {
            'restocked' => 'Restocked',
            'low_stock' => 'Low Stock',
            'out_of_stock' => 'Out of Stock',
            'action_required' => 'Action Needed',
            default => 'Logged',
        };
    }

    private function supplyStatusOrder(string $statusCode): int
    {
        return match ($statusCode) {
            'restocked' => 1,
            'low_stock' => 2,
            'out_of_stock' => 3,
            'action_required' => 4,
            default => 5,
        };
    }

    private function extractQuantity(?string $text): ?int
    {
        $value = trim((string) $text);
        if ($value === '') {
            return null;
        }

        if (preg_match('/\b(\d{1,3})\b/', $value, $matches) !== 1) {
            return null;
        }

        $quantity = (int) $matches[1];

        return $quantity > 0 ? $quantity : null;
    }

    private function containsAny(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($text, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function resolveCheckoutAt(CleaningSession $session): ?Carbon
    {
        if ($session->scheduled_time) {
            return $session->scheduled_time->copy();
        }

        if ($session->scheduled_date) {
            return $session->scheduled_date->copy()->setTime(11, 0);
        }

        return null;
    }

    private function resolveReadyByAt(CleaningSession $session): ?Carbon
    {
        if ($session->scheduled_date) {
            return $session->scheduled_date->copy()->setTime(15, 0);
        }

        return null;
    }

    private function extractUnitLabel(string $propertyName): ?string
    {
        if (preg_match('/(?:unit|apt|suite|#)\s*([a-z0-9-]+)/i', $propertyName, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    private function checklistKey(?int $roomId, ?int $taskId): string
    {
        return ($roomId === null ? 'property' : ('room-' . $roomId)) . '|task-' . ($taskId ?? 0);
    }

    private function resolveReportPhotoUrl(string $token, ?string $pathOrUrl): ?string
    {
        if ($pathOrUrl === null) {
            return null;
        }

        $candidate = trim($pathOrUrl);
        if ($candidate === '') {
            return null;
        }

        if (Str::startsWith($candidate, ['http://', 'https://', '//', 'data:'])) {
            return $candidate;
        }

        $normalizedPath = $this->normalizeStoragePath($candidate);
        if ($normalizedPath === null) {
            return null;
        }

        if (is_file(storage_path('app/public/' . $normalizedPath))) {
            return url('file/' . $normalizedPath);
        }

        return route('reports.sessions.photo', [
            'token' => $token,
            'path' => $normalizedPath,
        ]);
    }

    private function resolveReportPhotoDownloadUrl(string $token, ?string $pathOrUrl): ?string
    {
        if ($pathOrUrl === null) {
            return null;
        }

        $normalizedPath = $this->normalizeStoragePath($pathOrUrl);
        if ($normalizedPath === null) {
            return null;
        }

        if ($token === '') {
            return null;
        }

        return route('reports.sessions.photo.download', [
            'token' => $token,
            'path' => $normalizedPath,
        ]);
    }

    private function sessionHasPhotoPath(CleaningSession $session, string $normalizedPath): bool
    {
        return $session->photos()
            ->where('path', $normalizedPath)
            ->exists()
            || ChecklistItemPhoto::query()
                ->where('path', $normalizedPath)
                ->whereHas('checklistItem', fn($q) => $q->where('session_id', $session->id))
                ->exists();
    }

    private function safeFileSegment(string $value): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($value)) ?? '';
        $clean = trim($clean, '-_');

        return $clean === '' ? 'room' : $clean;
    }

    private function normalizeStoragePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $normalized = str_replace('\\', '/', trim($path));
        if ($normalized === '') {
            return null;
        }

        if (Str::startsWith($normalized, '/storage/')) {
            $normalized = substr($normalized, strlen('/storage/'));
        } elseif (Str::startsWith($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        $normalized = ltrim($normalized, '/');
        if ($normalized === '' || Str::contains($normalized, ['..', "\0"])) {
            return null;
        }

        return $normalized;
    }
}
