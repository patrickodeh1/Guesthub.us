@php
    $isPdfView = ($viewMode ?? 'web') === 'pdf';
    $generatedText = $generatedAt->format('M j, Y \a\t g:i A');
    $checkoutText = $checkoutAt?->format('M j, Y \a\t g:i A') ?? 'Not scheduled';
    $readyByText = $readyByAt?->format('M j, Y \a\t g:i A') ?? 'In progress';
    $endedAtDisplay = $endedAtForDisplay ?? null;
    $startDateText = $startedAtForDisplay?->format('M j, Y') ?? '--';
    $startTimeText = $startedAtForDisplay?->format('g:i A') ?? '--';
    $endDateText = $endedAtDisplay?->format('M j, Y') ?? '--';
    $endTimeText = $endedAtDisplay?->format('g:i A') ?? '--';
    $reportDisplayName = $reportName ?? ($session->property->name . ' - Cleaning - ' . ($cleaningDate?->format('M j, Y') ?? $generatedAt->format('M j, Y')));
    $reportDateText = $generatedAt->format('M j, Y');

    $themePrimary = \App\Models\Setting::get('theme_color', '#842eb8');
    $buttonPrimary = \App\Models\Setting::get('button_primary_color', $themePrimary);

    // Report section colors
    $rptHeader = \App\Models\Setting::get('report_header_color', '#842eb8');
    $rptStatus = \App\Models\Setting::get('report_status_color', '#0e7a4b');
    $rptChecklist = \App\Models\Setting::get('report_checklist_color', '#3b82f6');
    $rptIssues = \App\Models\Setting::get('report_issues_color', '#ef4444');
    $rptPhotos = \App\Models\Setting::get('report_photos_color', '#0e8a97');
    $rptTime = \App\Models\Setting::get('report_time_color', '#7c3aed');
    $rptSupplies = \App\Models\Setting::get('report_supplies_color', '#ec4899');
    $rptAudit = \App\Models\Setting::get('report_audit_color', '#64748b');
    $rptBtnPrimary = \App\Models\Setting::get('report_button_primary_color', '#842eb8');
    $rptBtnSecondary = \App\Models\Setting::get('report_button_secondary_color', '#ffffff');

    // Calculate contrasting text color for secondary button
    $rptBtnSecondaryText = (function (string $hex): string {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        // Relative luminance formula
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $luminance > 0.55 ? '#475467' : '#ffffff';
    })($rptBtnSecondary);

    // Secondary button border: if light bg, use a visible border; if dark bg, use same color
    $rptBtnSecondaryBorder = (function (string $hex): string {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $luminance > 0.85 ? '#d2dcea' : '#' . $hex;
    })($rptBtnSecondary);

    $propertyLine = collect([
        $session->property->name,
        $unitLabel ? ('Unit ' . $unitLabel) : null,
        $session->property->address,
    ])->filter(fn($value) => !empty($value))->implode(' | ');

    $statusTone = match ($overallStatusCode) {
        'ready' => 'tone-ready',
        'ready_with_exceptions' => 'tone-warn',
        'in_progress' => 'tone-info',
        default => 'tone-danger',
    };

    $restockedItems = collect($suppliesSummary['restocked_items'] ?? []);
    $lowItems = collect($suppliesSummary['low_or_missing_items'] ?? []);
    $ownerActionItems = collect($suppliesSummary['owner_action_items'] ?? []);
    $inventoryRows = collect($suppliesSummary['inventory_rows'] ?? []);
    $suppliesUsed = !empty($suppliesSummary['used']);

    $completionRooms = collect($completionGalleryByRoom ?? []);
    $completionPhotos = collect($allCompletionPhotos ?? []);
    $roomsWithPhotos = $completionRooms->filter(fn($room) => ($room['photo_count'] ?? 0) > 0)->values();

    $checklistSections = collect();
    if ($propertyItems->count() > 0) {
        $checklistSections->push([
            'id' => 'property-level',
            'name' => 'Property-level Tasks',
            'checked' => $propertyItems->where('checked', true)->count(),
            'total' => $propertyItems->count(),
            'items' => $propertyItems,
        ]);
    }

    foreach ($roomSections as $section) {
        $checklistSections->push($section);
    }

    $roomIcon = static function (string $name): string {
        $label = strtolower($name);

        return match (true) {
            str_contains($label, 'kitchen') => 'kitchen',
            str_contains($label, 'bath') => 'bath',
            str_contains($label, 'bed') => 'bed',
            str_contains($label, 'living') => 'living',
            str_contains($label, 'laundry') => 'laundry',
            str_contains($label, 'dining') => 'dining',
            str_contains($label, 'hall') => 'hall',
            str_contains($label, 'balcony') || str_contains($label, 'patio') => 'outdoor',
            str_contains($label, 'property') => 'property',
            default => 'room',
        };
    };

    $checkStatus = static function (int $checked, int $total): array {
        if ($total > 0 && $checked >= $total) {
            return ['code' => 'complete', 'text' => 'Complete'];
        }

        if ($checked > 0) {
            return ['code' => 'attention', 'text' => 'Attention'];
        }

        return ['code' => 'notdone', 'text' => 'Not Done'];
    };

    $overallStatusLabelCode = match ($overallStatusCode) {
        'ready' => 'complete',
        'ready_with_exceptions' => 'attention',
        'in_progress' => 'inprogress',
        default => 'notdone',
    };

    $actionItems = $exceptionRows
        ->filter(function ($issue) {
            return in_array($issue['priority'], ['Urgent', 'Normal'], true)
                || $issue['status'] === 'New'
                || in_array($issue['category'], ['Missing item', 'Maintenance', 'Damage'], true);
        })
        ->values();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    
    <!-- Favicon -->
    @php
        $faviconPath = \App\Models\Setting::get('favicon_path');
        if ($faviconPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($faviconPath)) {
            $faviconUrl = url('file/' . ltrim($faviconPath, '/'));
            $faviconExt = strtolower(pathinfo($faviconPath, PATHINFO_EXTENSION));
            $faviconType = match($faviconExt) {
                'ico' => 'image/x-icon',
                'png' => 'image/png',
                'svg' => 'image/svg+xml',
                'jpg', 'jpeg' => 'image/jpeg',
                default => 'image/x-icon',
            };
        }
    @endphp
    @if (isset($faviconUrl))
        <link rel="icon" type="{{ $faviconType }}" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" type="{{ $faviconType }}" href="{{ $faviconUrl }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @endif

    <title>{{ $reportDisplayName }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Lora:wght@500;600&display=swap');

        /* Timestamp overlay removed — metadata is now permanently embedded in photos */
        .timestamp {
            display: none !important;
        }

        :root {
            --theme-primary: {{ $themePrimary }};
            --button-primary: {{ $buttonPrimary }};
            --rpt-header: {{ $rptHeader }};
            --rpt-status: {{ $rptStatus }};
            --rpt-checklist: {{ $rptChecklist }};
            --rpt-issues: {{ $rptIssues }};
            --rpt-photos: {{ $rptPhotos }};
            --rpt-time: {{ $rptTime }};
            --rpt-supplies: {{ $rptSupplies }};
            --rpt-audit: {{ $rptAudit }};
            --rpt-btn-primary: {{ $rptBtnPrimary }};
            --rpt-btn-secondary: {{ $rptBtnSecondary }};
            --rpt-btn-secondary-text: {{ $rptBtnSecondaryText }};
            --rpt-btn-secondary-border: {{ $rptBtnSecondaryBorder }};
            --content-max: 1160px;
            --bg: #f4f6fa;
            --panel: #ffffff;
            --text: #101828;
            --text-soft: #475467;
            --line: #dce3ee;
            --line-soft: #e9eef5;
            --line-strong: #d2dcea;
            --navy: #0f1f3a;
            --navy-soft: #1d345f;
            --teal: #0e8a97;
            --teal-soft: #e3f5f7;
            --amber: #c47a1a;
            --amber-soft: #fef3e0;
            --gold: #b8862e;
            --gold-soft: #fdf6e8;
            --ok: #0e7a4b;
            --ok-soft: #e7f6ee;
            --warn: #b25f0e;
            --warn-soft: #fff1e4;
            --danger: #b42318;
            --danger-soft: #ffecea;
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --shadow-soft: 0 6px 16px rgba(16, 24, 40, 0.06);
            --font-body: "DM Sans", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            --font-display: "Lora", Georgia, "Times New Roman", serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--font-body);
            font-size: 14px;
            line-height: 1.58;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .shell {
            max-width: var(--content-max);
            margin: 0 auto;
            padding: 0 30px 68px;
        }

        .hero {
            background: var(--rpt-header);
            border: 0;
            border-radius: 0;
            overflow: hidden;
            box-shadow: none;
            margin-left: calc(50% - 50vw);
            margin-right: calc(50% - 50vw);
            margin-bottom: 0;
        }

        .hero-top {
            color: #ffffff;
            max-width: var(--content-max);
            margin: 0 auto;
            padding: 10px 30px 8px;
            display: block;
        }

        .report-title {
            font-family: var(--font-display);
            font-size: clamp(21px, 2.2vw, 28px);
            line-height: 1.2;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .report-property {
            font-size: 12px;
            opacity: 0.92;
            margin-bottom: 0;
        }

        .report-date {
            font-size: 12px;
            opacity: 0.9;
            margin-top: 3px;
        }

        .meta-grid {
            display: none;
        }

        .meta-grid strong {
            color: #ffffff;
            margin-right: 5px;
        }

        .hero-status {
            display: none;
        }

        .status-chip {
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.14);
            padding: 6px 12px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
            white-space: nowrap;
        }

        .tone-ready {
            color: #d8f6e8;
        }

        .tone-warn {
            color: #ffe3ba;
        }

        .tone-danger {
            color: #ffd5d2;
        }

        .tone-info {
            color: #d1e8ff;
        }

        .status-meta {
            font-size: 12px;
            text-align: right;
            opacity: 0.95;
            display: grid;
            gap: 2px;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 120;
            border: 1px solid var(--line-strong);
            background: #ffffff;
            border-radius: 0;
            box-shadow: 0 3px 12px rgba(16, 24, 40, 0.08);
            margin-left: calc(50% - 50vw);
            margin-right: calc(50% - 50vw);
            margin-bottom: 24px;
            border-left: 0;
            border-right: 0;
        }

        .toolbar-inner {
            max-width: var(--content-max);
            margin: 0 auto;
            padding: 9px 30px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px 14px;
            flex-wrap: nowrap;
        }

        .toolbar-path {
            display: none;
        }

        .toolbar-path-main {
            color: #344054;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .toolbar-path-dot {
            color: #98a2b3;
            margin: 0 1px;
        }

        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: flex-end;
            margin-left: auto;
            flex-wrap: wrap;
        }

        .actions-main,
        .actions-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .actions-secondary {
            margin-left: 4px;
        }

        .btn {
            border: 1px solid var(--rpt-btn-secondary-border);
            border-radius: 7px;
            background: var(--rpt-btn-secondary);
            color: var(--rpt-btn-secondary-text);
            font-size: 13px;
            font-weight: 500;
            padding: 8px 14px;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            transition: all 0.18s ease;
        }

        .btn:hover {
            filter: brightness(0.96);
            border-color: var(--rpt-btn-secondary-border);
            color: var(--rpt-btn-secondary-text);
        }

        .btn-primary {
            border-color: var(--rpt-btn-primary);
            background: var(--rpt-btn-primary);
            color: #ffffff;
        }

        .btn-primary:hover {
            filter: brightness(0.95);
            color: #ffffff;
        }

        .room-download-menu {
            position: relative;
        }

        .room-download-menu > summary {
            list-style: none;
            cursor: pointer;
        }

        .room-download-menu > summary::-webkit-details-marker {
            display: none;
        }

        .room-download-list {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            width: 230px;
            max-height: 260px;
            overflow: auto;
            padding: 8px;
            border: 1px solid var(--line);
            border-radius: 10px;
            box-shadow: 0 8px 18px rgba(16, 24, 40, 0.12);
            background: #ffffff;
            display: grid;
            gap: 6px;
        }

        .room-download-list a {
            border: 1px solid var(--line-soft);
            border-radius: 8px;
            padding: 6px 8px;
            font-size: 12px;
            color: var(--text-soft);
        }

        .section {
            margin-bottom: 56px;
            scroll-margin-top: 96px;
        }

        main {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        /* Section order: Summary → Issues → Compliance → Checklist → Photos → Supplies → Audit */
        #summary { order: 0; }
        #issues { order: 1; }
        #compliance-checks { order: 2; }
        #checklist { order: 3; }
        #gallery { order: 4; }
        #supplies { order: 5; }
        #signoff { order: 6; }

        .collapsible-section > summary {
            list-style: none;
            cursor: pointer;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            gap: 12px;
            user-select: none;
            font-size: 16px;
            font-weight: 600;
            color: var(--navy);
            border-bottom: 1px solid var(--line-soft);
            background: #ffffff;
        }
        .collapsible-section > summary::-webkit-details-marker { display: none; }
        .collapsible-section > summary .cs-chevron {
            width: 18px; height: 18px; color: #98a2b3; flex-shrink: 0; margin-left: auto;
            transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .collapsible-section > summary .cs-chevron svg { width:100%; height:100%; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
        .collapsible-section[open] > summary .cs-chevron { transform: rotate(180deg); }
        .collapsible-section > .cs-body { padding: 16px 18px; }

        .edited-banner {
            background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px;
            padding: 12px 16px; margin-bottom: 24px; color: #92400e;
            display: flex; align-items: center; gap: 12px; font-weight: 500; font-size: 14px;
        }

        .lightbox-nav {
            position: fixed; top: 50%; transform: translateY(-50%); z-index: 10001;
            width: 44px; height: 44px; border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.35); background: rgba(255,255,255,0.08);
            color: #fff; font-size: 22px; cursor: pointer; display: flex;
            align-items: center; justify-content: center;
        }
        .lightbox-nav:hover { background: rgba(255,255,255,0.2); }
        .lightbox-prev { left: 14px; }
        .lightbox-next { right: 14px; }
        .lightbox-counter {
            color: rgba(255,255,255,0.7); font-size: 12px; margin-top: 4px;
        }

        .section-head {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .section-head::after {
            content: '';
            order: 2;
            flex: 1;
            height: 1px;
            background: var(--line-strong);
        }

        /* Section-specific heading colors */
        #issues .section-head::after { background: var(--rpt-issues); }
        #checklist .section-head::after { background: var(--rpt-checklist); }
        #gallery .section-head::after { background: var(--rpt-photos); }
        #supplies .section-head::after { background: var(--rpt-supplies); }
        #signoff .section-head::after { background: var(--rpt-audit); }

        #issues .section-title { color: var(--rpt-issues); }
        #checklist .section-title { color: var(--rpt-checklist); }
        #gallery .section-title { color: var(--rpt-photos); }
        #supplies .section-title { color: var(--rpt-supplies); }
        #signoff .section-title { color: var(--rpt-audit); }

        .section-title {
            order: 1;
            font-family: var(--font-display);
            font-size: 20px;
            line-height: 1.25;
            font-weight: 600;
            color: var(--navy);
        }

        .section-sub {
            order: 3;
            font-size: 12px;
            color: #667085;
            line-height: 1.45;
            white-space: nowrap;
        }

        .summary-tiles {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
        }

        .summary-tile {
            border: none;
            border-radius: var(--radius-md);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
            padding: 28px 24px 24px;
            position: relative;
            overflow: hidden;
            transition: box-shadow 0.18s ease, transform 0.18s ease;
            color: #ffffff;
        }

        .summary-tile:hover {
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.22);
            transform: translateY(-2px);
        }

        .summary-link {
            display: block;
            color: inherit;
            text-decoration: none;
        }

        .summary-link:focus-visible {
            outline: 2px solid color-mix(in srgb, var(--theme-primary) 62%, #ffffff);
            outline-offset: 2px;
        }

        .summary-lbl {
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 8px;
        }

        .summary-val {
            font-size: 34px;
            line-height: 1;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        .summary-val .summary-unit {
            font-size: 14px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.7);
            margin-left: 2px;
        }

        .summary-val-text {
            font-size: 24px;
            line-height: 1.2;
        }

        .summary-sub {
            font-size: 12.5px;
            color: rgba(255, 255, 255, 0.75);
            line-height: 1.58;
        }

        .summary-bar {
            display: none;
        }

        /* Colored tile backgrounds */
        .tile-status-ready { background: var(--ok); }
        .tile-status-warn { background: var(--warn); }
        .tile-status-danger { background: var(--danger); }
        .tile-time { background: var(--rpt-time); }
        .tile-issues { background: var(--rpt-issues); }
        .tile-checklist { background: var(--rpt-checklist); }
        .tile-photos { background: var(--rpt-photos); }
        .tile-supplies { background: var(--rpt-supplies); }

        .summary-bar-ready {
            background: color-mix(in srgb, var(--theme-primary) 88%, #ffffff);
        }

        .summary-bar-checklist {
            background: color-mix(in srgb, var(--theme-primary) 76%, #0f172a);
        }

        .summary-bar-issues {
            background: var(--danger);
        }

        .summary-bar-photos {
            background: color-mix(in srgb, var(--theme-primary) 72%, #0e8a97);
        }

        .summary-bar-time {
            background: color-mix(in srgb, var(--theme-primary) 58%, #ffffff);
        }

        .summary-bar-supplies {
            background: color-mix(in srgb, var(--theme-primary) 70%, #2f3c62);
        }

        .summary-ready {
            color: #ffffff;
        }

        .summary-warn {
            color: #ffffff;
        }

        .summary-danger {
            color: #ffffff;
        }

        .metric-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
            color: var(--text-soft);
            line-height: 1.45;
        }

        .metric-value {
            font-size: 28px;
            line-height: 1.3;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: 0.01em;
        }

        .metric-sub {
            font-size: 12px;
            color: var(--text-soft);
            line-height: 1.6;
        }

        .status-label {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            line-height: 1.2;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .status-label-complete {
            background: var(--ok-soft);
            color: var(--ok);
            border-color: #b7e3cf;
        }

        .status-label-attention {
            background: var(--warn-soft);
            color: var(--warn);
            border-color: #efc99b;
        }

        .status-label-notdone {
            background: var(--danger-soft);
            color: var(--danger);
            border-color: #f2bfbb;
        }

        .status-label-inprogress {
            background: var(--info-soft, #d1e8ff);
            color: var(--info, #026aa2);
            border-color: #bee3f8;
        }

        .pdf-mode .status-label,
        .table-wrap {
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            background: #ffffff;
            overflow: auto;
        }

        .table-gap {
            margin-top: 12px;
        }

        .report-table {
            width: 100%;
            min-width: 720px;
            border-collapse: collapse;
        }

        .report-table thead th {
            text-align: left;
            padding: 12px 14px;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-soft);
            border-bottom: 1px solid var(--line-soft);
            background: #f8fafc;
            white-space: nowrap;
        }

        .report-table tbody td {
            padding: 13px 14px;
            font-size: 13px;
            color: #1f2937;
            border-bottom: 1px solid #eef2f7;
            vertical-align: top;
            line-height: 1.62;
        }

        .report-table tbody tr:last-child td {
            border-bottom: none;
        }

        .report-table .cell-key {
            font-weight: 600;
            color: #111827;
            white-space: nowrap;
        }

        .table-note {
            color: var(--text-soft);
            font-size: 12px;
            line-height: 1.58;
            margin-top: 4px;
        }

        .table-thumb {
            width: 62px;
            height: 46px;
            border-radius: 6px;
            border: 1px solid var(--line-soft);
            object-fit: cover;
            background: #f8fafc;
        }

        .table-thumbs {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .panel {
            border: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
            padding: 0;
        }

        .signoff-panel {
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            padding: 24px;
        }

        .empty {
            border: 1px dashed var(--line);
            border-radius: var(--radius-md);
            background: #fbfcfe;
            padding: 16px;
            font-size: 13px;
            color: var(--text-soft);
        }

        .issue-list {
            display: grid;
            gap: 12px;
        }

        .issue-item {
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            overflow: hidden;
            background: #ffffff;
        }

        .issue-item > summary {
            list-style: none;
            cursor: pointer;
            padding: 16px 18px;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: 12px;
            align-items: start;
            border-bottom: 1px solid var(--line-soft);
        }

        .issue-item > summary::-webkit-details-marker {
            display: none;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            white-space: nowrap;
            line-height: 1.2;
        }

        .priority-urgent {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .priority-normal {
            background: var(--warn-soft);
            color: var(--warn);
        }

        .priority-fyi {
            background: #e9eff8;
            color: #3b4d72;
        }

        .issue-state-new {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .issue-state-ack {
            background: var(--warn-soft);
            color: var(--warn);
        }

        .issue-state-resolved {
            background: var(--ok-soft);
            color: var(--ok);
        }

        .issue-title {
            font-size: 15px;
            line-height: 1.3;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .issue-line {
            font-size: 12px;
            color: var(--text-soft);
            margin-bottom: 3px;
        }

        .issue-desc {
            font-size: 13px;
            color: #1f2937;
        }

        .issue-body {
            padding: 16px 18px 18px;
            display: grid;
            gap: 12px;
        }

        .issue-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 8px;
        }

        .meta-box {
            border: 1px solid var(--line-soft);
            border-radius: 8px;
            background: #fafcff;
            padding: 8px;
        }

        .meta-key {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-soft);
            margin-bottom: 3px;
            font-weight: 700;
        }

        .meta-value {
            font-size: 13px;
            color: #1f2937;
        }

        .note-box {
            border: 1px solid var(--line-soft);
            border-radius: 8px;
            background: #f9fbff;
            padding: 10px;
            font-size: 13px;
            color: #1f2937;
        }

        .photo-thumb-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 8px;
        }

        .photo-thumb {
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
            background: #f8fafc;
            cursor: pointer;
            padding: 0;
            position: relative;
        }

        .photo-thumb img {
            width: 100%;
            height: 100%;
            aspect-ratio: 4 / 3;
            object-fit: cover;
            display: block;
        }

        .issue-downloads {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .issue-downloads a {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 6px 9px;
            font-size: 12px;
            color: var(--text-soft);
            background: #ffffff;
        }

        .gallery {
            display: flex;
            flex-direction: column;
            gap: 34px;
        }

        .gallery-room {
            margin: 0;
        }

        .gallery-room-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .room-lbl {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            color: #98a2b3;
            flex: 1;
            min-width: 180px;
        }

        .room-lbl .ic {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            border: 1px solid var(--line);
            background: #f6f9fc;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #334155;
            flex-shrink: 0;
        }

        .room-lbl .ic svg {
            width: 12px;
            height: 12px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .room-lbl::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--line-strong);
        }

        .pairs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 14px;
        }

        .pair-card {
            background: #ffffff;
            border-radius: var(--radius-md);
            border: 1px solid var(--line-strong);
            box-shadow: 0 2px 8px rgba(16, 24, 40, 0.06);
            overflow: hidden;
            transition: box-shadow 0.18s ease;
        }

        .pair-card:hover {
            box-shadow: 0 8px 18px rgba(16, 24, 40, 0.1);
        }

        .pair-slot {
            width: 100%;
            border: 0;
            background: #f3f6fb;
            cursor: pointer;
            padding: 0;
            position: relative;
            aspect-ratio: 4 / 3;
            overflow: hidden;
        }

        .pair-slot img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.22s ease;
        }

        .pair-slot:hover img {
            transform: scale(1.03);
        }

        .pair-footer {
            padding: 12px 14px;
            border-top: 1px solid #eef2f7;
            display: grid;
            gap: 6px;
        }

        .pair-room {
            font-size: 13px;
            font-weight: 600;
            color: #1f2937;
            line-height: 1.35;
        }

        .pair-time {
            font-size: 11.5px;
            color: #667085;
            line-height: 1.45;
            display: grid;
            gap: 2px;
        }

        .pair-actions {
            display: flex;
            gap: 6px;
            margin-top: 4px;
        }

        .pair-actions a,
        .pair-actions button {
            flex: 1;
            border: 1px solid var(--line);
            border-radius: 7px;
            background: #ffffff;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
            padding: 6px 8px;
            cursor: pointer;
            color: #475467;
        }

        .checklist-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .check-section {
            border: 1px solid var(--line-strong);
            border-radius: var(--radius-md);
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
            overflow: hidden;
            transition: box-shadow 0.18s ease;
        }

        .check-section-complete {
            border-color: #b7e3cf;
        }

        .check-section-attention {
            border-color: #efc99b;
        }

        .check-section-notdone {
            border-color: #f2bfbb;
        }

        .check-section[open] {
            box-shadow: 0 4px 14px rgba(16, 24, 40, 0.08);
        }

        .check-section > summary {
            list-style: none;
            cursor: pointer;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            user-select: none;
        }

        .check-section > summary::-webkit-details-marker {
            display: none;
        }

        .check-summary-main {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 0;
        }

        .room-icon {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            background: #f8fafc;
            border: 1px solid #d9e2ef;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #334155;
            flex-shrink: 0;
        }

        .check-section-complete .room-icon {
            background: var(--ok-soft);
            border-color: #b7e3cf;
            color: var(--ok);
        }

        .check-section-attention .room-icon {
            background: var(--warn-soft);
            border-color: #efc99b;
            color: var(--warn);
        }

        .check-section-notdone .room-icon {
            background: var(--danger-soft);
            border-color: #f2bfbb;
            color: var(--danger);
        }

        .room-icon svg {
            width: 15px;
            height: 15px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .check-room-name {
            font-size: 14px;
            line-height: 1.35;
            color: #1f2937;
            font-weight: 600;
        }

        .check-summary-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11.5px;
            color: #98a2b3;
            margin-left: auto;
        }

        .check-pbar {
            width: 70px;
            height: 4px;
            border-radius: 999px;
            background: #edf2f7;
            overflow: hidden;
            flex-shrink: 0;
        }

        .check-pbar-fill {
            height: 100%;
            border-radius: 999px;
            background: var(--ok);
            transition: width 0.28s ease;
        }

        .check-section-complete .check-pbar-fill {
            background: var(--ok);
        }

        .check-section-attention .check-pbar-fill {
            background: var(--warn);
        }

        .check-section-notdone .check-pbar-fill {
            background: var(--danger);
        }

        .check-count {
            font-size: 11.5px;
            color: #667085;
            font-weight: 500;
            white-space: nowrap;
        }

        .check-chevron {
            width: 14px;
            height: 14px;
            color: #98a2b3;
            flex-shrink: 0;
            transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .check-chevron svg {
            width: 100%;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .check-section[open] .check-chevron {
            transform: rotate(180deg);
        }

        .check-section[data-closing="1"] .check-chevron {
            transform: rotate(0deg);
        }

        .check-body {
            border-top: 1px solid #eef2f7;
            padding: 4px 0;
        }

        .task-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 8px 16px;
            border-bottom: 1px solid #f6f8fb;
            transition: background 0.16s ease;
        }

        .task-row:last-child {
            border-bottom: none;
        }

        .task-check {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            border: 1.5px solid #cbd5e1;
            flex-shrink: 0;
            margin-top: 1px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: transparent;
        }

        .task-check.checked {
            background: var(--ok);
            border-color: var(--ok);
            color: #ffffff;
        }

        .task-check.skipped {
            background: var(--warn);
            border-color: var(--warn);
            color: #ffffff;
        }

        .task-check svg {
            width: 10px;
            height: 10px;
            stroke: currentColor;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .task-content {
            flex: 1;
            min-width: 0;
        }

        .task-name {
            font-size: 12.5px;
            font-weight: 500;
            color: #1f2937;
            line-height: 1.45;
        }

        .task-inline {
            font-size: 11.5px;
            color: #667085;
            line-height: 1.5;
            margin-top: 3px;
        }

        .task-time {
            font-size: 11.5px;
            color: #98a2b3;
            white-space: nowrap;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .task-note {
            margin-top: 4px;
            font-size: 11.5px;
            color: #667085;
            background: #f8fafc;
            padding: 6px 10px;
            border-radius: 4px;
            border-left: 2px solid #cbd5e1;
        }

        .task-row:hover {
            background: #f8fafd;
        }

        .task-state {
            width: 20px;
            height: 20px;
            border-radius: 999px;
            border: 1.5px solid #cbd5e1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 800;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .task-ok {
            color: var(--ok);
            background: #e8f6ef;
            border-color: #b6e2cf;
        }

        .task-attention {
            color: var(--warn);
            background: #fff4e8;
            border-color: #f2cfa5;
        }

        .task-open {
            color: var(--danger);
            background: #feeeed;
            border-color: #f4c1bd;
        }

        .task-info {
            flex: 1;
            min-width: 0;
        }

        .task-name {
            font-size: 13.5px;
            font-weight: 500;
            color: #1f2937;
            line-height: 1.45;
        }

        .task-inline {
            font-size: 12px;
            color: #667085;
            line-height: 1.5;
            margin-top: 3px;
        }

        .task-time {
            font-size: 12px;
            color: #98a2b3;
            white-space: nowrap;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .task-note {
            margin-top: 4px;
            font-size: 12px;
            color: #667085;
            line-height: 1.52;
        }

        .task-photo-grid {
            margin-top: 8px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(42px, 1fr));
            gap: 6px;
            max-width: 220px;
        }

        .task-photo-grid button {
            border: 1px solid var(--line);
            border-radius: 7px;
            overflow: hidden;
            background: #ffffff;
            padding: 0;
            cursor: pointer;
            width: 42px;
            height: 42px;
        }

        .task-photo-grid img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .supplies-panel {
            padding: 0;
        }

        .supplies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .supply-card {
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            background: #ffffff;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .supply-card-head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px 16px 14px;
            border-bottom: 1px solid var(--line-soft);
        }

        .supply-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .supply-icon-ok {
            background: var(--ok-soft);
            color: var(--ok);
        }

        .supply-icon-warn {
            background: var(--warn-soft);
            color: var(--warn);
        }

        .supply-icon-danger {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .supply-count {
            font-size: 18px;
            line-height: 1.1;
            font-weight: 700;
            color: #0f172a;
            margin-top: 2px;
        }

        .supply-list {
            display: grid;
            gap: 0;
        }

        .supply-entry {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
        }

        .supply-entry:last-child {
            border-bottom: none;
        }

        .supply-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        .supply-name {
            font-size: 13px;
            line-height: 1.35;
            color: #1f2937;
            font-weight: 600;
        }

        .supply-item-meta {
            margin-top: 4px;
            font-size: 12px;
            color: var(--text-soft);
            display: grid;
            gap: 2px;
        }

        .supply-empty {
            padding: 12px 13px;
            font-size: 13px;
            color: var(--text-soft);
        }

        .supply-pill {
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 11px;
            line-height: 1.2;
            font-weight: 700;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .supply-pill-ok {
            background: var(--ok-soft);
            color: var(--ok);
        }

        .supply-pill-warn {
            background: var(--warn-soft);
            color: var(--warn);
        }

        .supply-pill-danger {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .supply-pill-info {
            background: #eaf2ff;
            color: #175cd3;
        }

        .supply-table-wrap {
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            background: #ffffff;
            overflow: auto;
        }

        .supply-table {
            width: 100%;
            min-width: 680px;
            border-collapse: collapse;
        }

        .supply-table thead th {
            text-align: left;
            padding: 10px 12px;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-soft);
            border-bottom: 1px solid var(--line-soft);
            background: #f8fafc;
            white-space: nowrap;
        }

        .supply-table tbody td {
            padding: 10px 12px;
            font-size: 13px;
            color: #1f2937;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }

        .supply-table tbody tr:last-child td {
            border-bottom: none;
        }

        .supply-qty {
            width: 60px;
            text-align: center;
            font-weight: 700;
            color: #111827;
        }

        .supply-note {
            color: var(--text-soft);
            font-size: 12px;
            line-height: 1.4;
        }

        .signoff-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
        }

        .sig-check {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #1f2937;
            margin-top: 16px;
        }

        .sig-box {
            width: 16px;
            height: 16px;
            border: 1px solid var(--line);
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--ok);
            font-size: 11px;
            font-weight: 700;
        }

        .timeline {
            display: grid;
            gap: 6px;
            margin-top: 8px;
        }

        .timeline-row {
            border-bottom: 1px solid var(--line-soft);
            padding-bottom: 6px;
            display: grid;
            grid-template-columns: 58px 1fr;
            gap: 8px;
        }

        .timeline-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .timeline-time {
            font-size: 12px;
            color: var(--text-soft);
            font-weight: 600;
        }

        .timeline-text {
            font-size: 13px;
            color: #1f2937;
        }

        .signoff-identity {
            display: grid;
            gap: 12px;
        }

        .signoff-row {
            display: grid;
            gap: 4px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--line-soft);
        }

        .signoff-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .signoff-key {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
            color: var(--text-soft);
        }

        .signoff-value {
            font-size: 18px;
            line-height: 1.35;
            font-weight: 700;
            color: #0f172a;
        }

        .signoff-value-secondary {
            font-size: 14px;
            line-height: 1.45;
            font-weight: 600;
            color: #1f2937;
        }

        .signoff-meta-grid {
            margin-top: 14px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .signoff-meta-item {
            border: 1px solid var(--line-soft);
            border-radius: 8px;
            padding: 8px 9px;
            background: #fbfcff;
            display: grid;
            gap: 3px;
        }

        .signoff-meta-key {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--text-soft);
            font-weight: 700;
        }

        .signoff-meta-value {
            font-size: 13px;
            line-height: 1.4;
            color: #1f2937;
            font-weight: 600;
        }

        .muted {
            font-size: 12px;
            color: var(--text-soft);
        }

        .lightbox {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(8, 14, 28, 0.92);
        }

        .lightbox.on {
            display: flex;
        }

        .lightbox-inner {
            width: 100%;
            max-width: min(1100px, 95vw);
        }

        .lightbox-image {
            width: 100%;
            max-height: 82vh;
            object-fit: contain;
            border-radius: 10px;
            background: #0f172a;
        }

        .lightbox-meta {
            margin-top: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            color: rgba(255, 255, 255, 0.92);
            font-size: 13px;
            text-align: center;
        }

        .lightbox-download {
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 12px;
            color: #ffffff;
        }

        .lightbox-close {
            position: fixed;
            top: 14px;
            right: 14px;
            width: 38px;
            height: 38px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.35);
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            font-size: 18px;
            cursor: pointer;
        }

        .pdf-mode {
            --pdf-text: #1f2937;
            --pdf-muted: #4b5563;
            --pdf-line: #d7dfeb;
            --pdf-soft: #eef2f7;
            background: #ffffff;
            color: var(--pdf-text);
            font-family: "Helvetica Neue", "Segoe UI", Arial, sans-serif;
        }

        .pdf-mode .shell {
            max-width: 760px;
            padding: 18px 16px 26px;
        }

        .pdf-mode .hero {
            border-color: var(--pdf-line);
            box-shadow: none;
            border-radius: 12px;
            margin-bottom: 14px;
            margin-left: 0;
            margin-right: 0;
            border-left: 1px solid var(--pdf-line);
            border-right: 1px solid var(--pdf-line);
            border-bottom: 1px solid var(--pdf-line);
        }

        .pdf-mode .hero-top {
            background: #ffffff;
            color: var(--pdf-text);
            grid-template-columns: 1fr;
            gap: 12px;
            max-width: none;
            margin: 0;
            padding: 16px 16px 14px;
        }

        .pdf-mode .report-title,
        .pdf-mode .section-title {
            font-family: Georgia, "Times New Roman", serif;
            font-weight: 600;
            letter-spacing: 0.01em;
        }

        .pdf-mode .report-title {
            font-size: 30px;
            color: #111827;
        }

        .pdf-mode .supply-icon,
        .pdf-mode .task-state {
            display: none !important;
        }

        .pdf-mode .supply-card-head {
            padding: 12px 14px 10px;
            gap: 0;
        }

        .pdf-mode .task-row > summary {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .pdf-mode .task-body {
            padding-left: 18px;
        }

        .pdf-mode .report-property,
        .pdf-mode .status-meta {
            color: var(--pdf-muted);
        }

        .pdf-mode .meta-grid {
            color: var(--pdf-muted);
        }

        .pdf-mode .meta-grid strong {
            color: var(--pdf-text);
        }

        .pdf-mode .hero-status {
            align-items: flex-start;
        }

        .pdf-mode .status-chip {
            border-color: var(--pdf-line);
            background: var(--pdf-soft);
            color: var(--pdf-text);
        }

        .pdf-mode .toolbar {
            position: static;
            border-style: solid;
            border-color: var(--pdf-line);
            background: #ffffff;
            box-shadow: none;
            margin-bottom: 14px;
            margin-left: 0;
            margin-right: 0;
            border-left: 1px solid var(--pdf-line);
            border-right: 1px solid var(--pdf-line);
            border-radius: 12px;
        }

        .pdf-mode .toolbar-inner {
            max-width: none;
            margin: 0;
            gap: 8px;
            padding: 10px 12px;
        }

        .pdf-mode .section {
            margin-bottom: 18px;
        }

        .pdf-mode .section-head {
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--pdf-line);
        }

        .pdf-mode .section-head::after {
            display: none;
        }

        .pdf-mode #issues .section-head { border-bottom-color: var(--rpt-issues); }
        .pdf-mode #checklist .section-head { border-bottom-color: var(--rpt-checklist); }
        .pdf-mode #gallery .section-head { border-bottom-color: var(--rpt-photos); }
        .pdf-mode #supplies .section-head { border-bottom-color: var(--rpt-supplies); }
        .pdf-mode #signoff .section-head { border-bottom-color: var(--rpt-audit); }

        .pdf-mode .section-title {
            font-size: 18px;
        }

        .pdf-mode .section-sub {
            font-size: 11px;
            color: var(--pdf-muted);
        }

        .pdf-mode .room-icon {
            display: none !important;
        }

        .pdf-mode .signoff-grid {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .pdf-mode .metric-label {
            font-size: 10px;
            letter-spacing: 0.07em;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--pdf-muted);
            font-family: "Helvetica Neue", "Segoe UI", Arial, sans-serif;
        }

        .pdf-mode .metric-value {
            font-size: 22px;
            color: #111827;
            font-family: Georgia, "Times New Roman", serif;
            font-weight: 600;
            letter-spacing: 0.01em;
        }

        .pdf-mode .metric-sub {
            font-size: 11px;
            line-height: 1.35;
            color: var(--pdf-muted);
        }

        .pdf-mode .table-wrap {
            border-color: var(--pdf-line);
            border-radius: 10px;
            box-shadow: none;
        }

        .pdf-mode .report-table {
            min-width: 0;
        }

        .pdf-mode .report-table thead th {
            padding: 8px 9px;
            font-size: 9px;
            letter-spacing: 0.08em;
            color: var(--pdf-muted);
            border-bottom-color: var(--pdf-line);
            background: #f8fafc;
        }

        .pdf-mode .report-table tbody td {
            padding: 8px 9px;
            font-size: 10.5px;
            color: var(--pdf-text);
            border-bottom-color: #e5eaf2;
            line-height: 1.5;
        }

        .pdf-mode .report-table .cell-key {
            color: #111827;
        }

        .pdf-mode .table-note {
            font-size: 10px;
            color: var(--pdf-muted);
            line-height: 1.45;
        }

        .pdf-mode .table-thumb {
            width: 52px;
            height: 38px;
            border-color: var(--pdf-line);
        }

        .pdf-mode .issue-title,
        .pdf-mode .gallery-room-title,
        .pdf-mode .supply-name {
            color: #111827;
            font-weight: 600;
        }

        .pdf-mode .issue-line,
        .pdf-mode .issue-desc,
        .pdf-mode .meta-value,
        .pdf-mode .note-box,
        .pdf-mode .task-inline,
        .pdf-mode .task-time,
        .pdf-mode .task-body,
        .pdf-mode .supply-item-meta,
        .pdf-mode .supply-note,
        .pdf-mode .photo-meta {
            color: var(--pdf-muted);
        }

        .pdf-mode .panel {
            padding: 0;
        }

        .pdf-mode .signoff-panel {
            border-color: var(--pdf-line);
            padding: 14px;
            border-radius: 10px;
        }

        .pdf-mode .timeline {
            margin-top: 6px;
            gap: 4px;
        }

        .pdf-mode .timeline-row {
            grid-template-columns: 52px 1fr;
            padding-bottom: 4px;
        }

        .pdf-mode .timeline-time,
        .pdf-mode .timeline-text {
            color: var(--pdf-muted);
        }

        .pdf-mode .pill,
        .pdf-mode .supply-pill,
        .pdf-mode .status-chip {
            border: 1px solid #c7d0dc;
            background: #f4f6f9;
            color: #374151;
            box-shadow: none;
        }

        .pdf-mode .priority-urgent,
        .pdf-mode .issue-state-new,
        .pdf-mode .supply-pill-danger,
        .pdf-mode .tone-danger,
        .pdf-mode .tone-info {
            border-color: #9ca3af;
            background: #eceff3;
            color: #111827;
        }

        .pdf-mode .priority-normal,
        .pdf-mode .issue-state-ack,
        .pdf-mode .supply-pill-warn,
        .pdf-mode .tone-warn {
            border-color: #b9c2cf;
            background: #f2f4f7;
            color: #374151;
        }

        .pdf-mode .priority-fyi,
        .pdf-mode .issue-state-resolved,
        .pdf-mode .supply-pill-ok,
        .pdf-mode .supply-pill-info,
        .pdf-mode .tone-ready {
            border-color: #cbd5e1;
            background: #f8fafc;
            color: #4b5563;
        }

        .pdf-mode .status-label,
        .pdf-mode .status-label-complete,
        .pdf-mode .status-label-attention,
        .pdf-mode .status-label-inprogress,
        .pdf-mode .status-label-notdone {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #374151;
        }

        .pdf-mode .issue-item > summary,
        .pdf-mode .check-section > summary,
        .pdf-mode .task-row > summary {
            cursor: default;
        }

        .pdf-mode .room-download-menu {
            display: none !important;
        }

        .pdf-mode .issue-item,
        .pdf-mode .gallery-room,
        .pdf-mode .check-section,
        .pdf-mode .supply-card,
        .pdf-mode .signoff-panel {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .pdf-mode .no-pdf {
            display: none !important;
        }

        @media (max-width: 960px) {
            .shell {
                padding: 0 16px 64px;
            }

            .hero-top,
            .signoff-grid {
                grid-template-columns: 1fr;
            }

            .hero-status,
            .status-meta {
                align-items: flex-start;
                text-align: left;
            }

            .meta-grid {
                grid-template-columns: 1fr;
            }

            .toolbar {
                position: static;
            }

            .toolbar-inner {
                padding: 9px 16px;
                flex-wrap: wrap;
            }

            .toolbar-path {
                width: 100%;
            }

            .actions {
                width: 100%;
                margin-left: 0;
                justify-content: space-between;
            }

            .actions-secondary {
                margin-left: auto;
            }

            .section-head {
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 18px;
            }

            .section-head::after {
                order: 3;
                flex-basis: 100%;
            }

            .section-sub {
                order: 4;
                width: 100%;
                white-space: normal;
            }

            .summary-tiles {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .check-section > summary {
                padding: 14px 16px;
            }

            .check-summary-meta {
                margin-left: 0;
            }

            .check-pbar {
                width: 64px;
            }

            .pairs-grid {
                grid-template-columns: 1fr;
            }

            .task-row {
                padding: 12px 16px;
            }

            .task-name {
                font-size: 12px;
            }

            .task-inline {
                font-size: 11px;
            }

            .signoff-meta-grid {
                grid-template-columns: 1fr;
            }

            .pairs-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 560px) {
            .summary-tiles {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .actions {
                gap: 8px;
            }

            .actions-main,
            .actions-secondary {
                width: 100%;
                justify-content: flex-start;
            }

            .actions-secondary {
                margin-left: 0;
            }

            .summary-tile {
                padding: 16px 12px 14px;
            }

            .summary-val {
                font-size: 24px;
            }

            .summary-val-text {
                font-size: 17px;
            }
        }

        @page {
            size: A4;
            margin: 12mm;
        }

        @media print {
            body {
                background: #ffffff;
                font-size: 10.5pt;
                line-height: 1.45;
            }

            .shell {
                max-width: none;
                padding: 0;
            }

            .lightbox,
            .no-print {
                display: none !important;
            }

            .toolbar {
                display: none !important;
            }

            .hero,
            .panel,
            .gallery-room,
            .supply-card,
            .check-section,
            .issue-item {
                box-shadow: none;
            }

            .section,
            .hero,
            .signoff-panel,
            .gallery-room,
            .issue-item,
            .check-section,
            .supply-card {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            details:not([open]) > *:not(summary) {
                display: block;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css">
</head>
<body class="{{ $isPdfView ? 'pdf-mode' : '' }}">
    @php
        $cleanerNameStr = $session->housekeeper?->name ?? 'Housekeeper';
        $propNameStr = $session->property->name ?? 'Property';
        $lat = $session->start_latitude;
        $lng = $session->start_longitude;
        $gpsStr = '';
        if ($lat && $lng) {
            $latDir = $lat >= 0 ? 'N' : 'S';
            $lngDir = $lng >= 0 ? 'E' : 'W';
            $gpsStr = sprintf('GPS: %.5f°%s, %.5f°%s', abs($lat), $latDir, abs($lng), $lngDir);
        }
    @endphp
    <div class="shell">
        <header class="hero">
            <div class="hero-top">
                <div>
                    <h1 class="report-title">{{ $reportDisplayName }}</h1>
                    <div class="report-date">Report Date: {{ $reportDateText }}</div>
                </div>
            </div>

        </header>

        <div class="toolbar">
            <div class="toolbar-inner">
                <div class="actions">
                    <div class="actions-main">
                        @if(!$isPdfView)
                            @if(auth()->check() && auth()->user()->hasAnyRole(['admin', 'owner', 'company']) && !$isComplete)
                                <button type="button" class="btn" style="background: #ef4444; color: #fff; border-color: #ef4444;" onclick="document.getElementById('override-modal').style.display='flex'">Manager Override - Close Session</button>
                            @endif
                            {{-- <button type="button" class="btn" onclick="window.print()">Print</button> --}}
                            <a class="btn btn-primary" href="{{ $pdfUrl }}">Save PDF</a>
                            <button type="button" class="btn" onclick="copyReportLink()">Copy Link</button>
                        @else
                            <button type="button" class="btn" onclick="window.print()">Print</button>
                            <a class="btn" href="{{ $webUrl }}">Web View</a>
                            <button type="button" class="btn" onclick="copyReportLink()">Copy Link</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <main>
            @if(!$isComplete)
                <div style="background: #e0f2fe; border: 1px solid #7dd3fc; border-radius: 8px; padding: 12px 16px; margin-bottom: 24px; color: #0369a1; display: flex; align-items: center; gap: 12px; font-weight: 500; font-size: 14px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <div>
                        <strong>Live Progress Report:</strong> This session is currently in progress. The information shown reflects only the work completed so far and is not a finalized report.
                    </div>
                </div>
            @endif

            @if($isComplete && $session->ended_at)
                @php
                    $postClosureActivities = \Spatie\Activitylog\Models\Activity::with('causer')
                        ->where('subject_type', get_class($session))
                        ->where('subject_id', $session->id)
                        ->where('created_at', '>', $session->ended_at)
                        ->latest()
                        ->get()
                        ->filter(function($activity) {
                            return $activity->causer && method_exists($activity->causer, 'hasAnyRole') && $activity->causer->hasAnyRole(['admin', 'company', 'owner']);
                        });
                        
                    $postClosureEditsCount = $postClosureActivities->count();
                    $adminEdit = $postClosureActivities->first();
                @endphp
                @if($adminEdit)
                    <div class="edited-banner">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        <div>
                            <strong>Report Updated After Session Closure</strong><br>
                            Edited by: {{ $adminEdit->causer->name ?? 'Admin' }}<br>
                            Edited at: {{ \App\Helpers\TimezoneHelper::format($adminEdit->created_at, $session->property) }}
                            @if($postClosureEditsCount > 1)
                                <br><span style="font-size: 13px; opacity: 0.9; margin-top: 4px; display: inline-block;">(Modified {{ $postClosureEditsCount }} times after closure)</span>
                            @endif
                        </div>
                    </div>
                @endif
            @endif

            <section id="summary" class="section">
                <div class="section-head">
                    <h2 class="section-title">Summary</h2>
                    <div class="section-sub">At-a-glance service outcome</div>
                </div>

                @if($isPdfView)
                    <div class="table-wrap">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Summary Metric</th>
                                    <th>Value</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="cell-key">Status</td>
                                    <td>
                                        <span class="status-label status-label-{{ $overallStatusLabelCode }}">{{ $overallStatusText }}</span>
                                    </td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td class="cell-key">Checklist Completion</td>
                                    <td>{{ $checkedChecklist }}/{{ $totalChecklist }}</td>
                                    <td>{{ $completionRate }}% complete</td>
                                </tr>
                                <tr>
                                    <td class="cell-key">Issues Logged</td>
                                    <td>{{ $issuesCount }}</td>
                                    <td>{{ $urgentIssues }} urgent | {{ $normalIssues }} normal | {{ $fyiIssues }} FYI</td>
                                </tr>
                                <tr>
                                    <td class="cell-key">Photos</td>
                                    <td>{{ $completionPhotosCount }}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td class="cell-key">Start</td>
                                    <td>{{ $startDateText }}</td>
                                    <td>{{ $startTimeText }}</td>
                                </tr>
                                <tr>
                                    <td class="cell-key">End</td>
                                    <td>{{ $endDateText }}</td>
                                    <td>{{ $endTimeText }} <span style="color: var(--text-soft);">(Duration: {{ $durationLabel }})</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @else
                    @php
                        $summaryStatusClass = match ($overallStatusCode) {
                            'ready' => 'summary-ready',
                            'ready_with_exceptions' => 'summary-warn',
                            default => 'summary-danger',
                        };
                        $tileBgClass = match ($overallStatusCode) {
                            'ready' => 'tile-status-ready',
                            'ready_with_exceptions' => 'tile-status-warn',
                            default => 'tile-status-danger',
                        };
                    @endphp
                    <div class="summary-tiles">
                        <a class="summary-tile {{ $tileBgClass }} summary-link" href="#issues">
                            <div class="summary-lbl">Status</div>
                            <div class="summary-val summary-val-text {{ $summaryStatusClass }}">{{ $overallStatusText }}</div>
                            <div class="summary-bar summary-bar-ready" style="display: none;"></div>
                        </a>

                        <a class="summary-tile tile-time summary-link" href="#signoff">
                            <div class="summary-lbl">Duration</div>
                            <div class="summary-val summary-val-text" style="font-size: 18px; line-height: 1.3;">
                                <div style="font-size: 13px; color: rgba(255, 255, 255, 0.75);">
                                    @if($startDateText === $endDateText)
                                        {{ $startDateText }}
                                    @else
                                        {{ $startDateText }} - {{ $endDateText }}
                                    @endif
                                </div>
                                <div>{{ $startTimeText }} - {{ $endTimeText }}</div>
                            </div>
                            <div class="summary-sub">Total time: {{ $durationLabel }}</div>
                            <div class="summary-bar summary-bar-time"></div>
                        </a>

                        <a class="summary-tile tile-issues summary-link" href="#issues">
                            <div class="summary-lbl">Issues</div>
                            <div class="summary-val">{{ $issuesCount }}</div>
                            <div class="summary-sub">{{ $urgentIssues }} urgent | {{ $normalIssues }} normal | {{ $fyiIssues }} FYI</div>
                            <div class="summary-bar summary-bar-issues"></div>
                        </a>

                        <a class="summary-tile tile-checklist summary-link" href="#checklist">
                            <div class="summary-lbl">Checklist</div>
                            <div class="summary-val">{{ $checkedChecklist }}<span class="summary-unit">/{{ $totalChecklist }}</span></div>
                            <div class="summary-sub">{{ $completionRate }}% complete</div>
                            <div class="summary-bar summary-bar-checklist"></div>
                        </a>

                        <a class="summary-tile tile-photos summary-link" href="#gallery">
                            <div class="summary-lbl">Photos</div>
                            <div class="summary-val">{{ $completionPhotosCount }}</div>
                            <div class="summary-bar summary-bar-photos"></div>
                        </a>

                    </div>
                @endif
            </section>

            <section id="issues" class="section">
                <div class="section-head">
                    <h2 class="section-title">Issues</h2>
                    <div class="section-sub">{{ $issuesCount }} issue{{ $issuesCount !== 1 ? 's' : '' }} logged</div>
                </div>

                <details class="collapsible-section" style="border: 1px solid var(--line); border-radius: var(--radius-md); overflow: hidden; background: #ffffff;" {!! $isPdfView ? 'open' : '' !!}>
                    <summary>
                        View Issues ({{ $issuesCount }})
                        <span class="cs-chevron"><svg viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></span>
                    </summary>
                    <div class="cs-body">
                        <div class="panel" style="border: none; box-shadow: none;">
                            @if($exceptionRows->count() === 0)
                                <div class="empty">No issues found.</div>
                            @else
                                <div class="issue-list">
                                    @foreach($exceptionRows->groupBy('location') as $location => $locationIssues)
                                <h3 style="font-size: 16px; font-weight: 600; margin: 16px 0 8px; color: var(--navy);">{{ $location }}</h3>
                                @foreach($locationIssues as $issue)
                                    @php
                                        $photoCount = $issue['photo_count'] ?? 0;
                                        $issuePhotos = $issue['photos'] ?? collect();
                                        $firstPhoto = $issuePhotos->first();
                                        $firstCapturedAt = $firstPhoto['captured_at'] ?? null;
                                        $timestamp = $firstCapturedAt ? \App\Helpers\TimezoneHelper::toLocal($firstCapturedAt, $propertyTz)?->format('m/d/Y - g:i A (T)') ?? 'No timestamp' : 'No timestamp';
                                        $priorityClass = match($issue['priority'] ?? 'Normal') {
                                            'Urgent' => 'priority-urgent',
                                            'Normal' => 'priority-normal',
                                            default => 'priority-fyi',
                                        };
                                    @endphp
                                    <div class="issue-item" style="padding: 16px 18px; border: 1px solid var(--line); border-radius: var(--radius-md); overflow: hidden; background: #ffffff; margin-bottom: 12px;">
                                        <div style="display: flex; gap: 12px; align-items: start;">
                                            @if(($issue['priority'] ?? 'Normal') !== 'FYI')
                                                <span class="pill {{ $priorityClass }}">{{ $issue['priority'] ?? 'Normal' }}</span>
                                            @endif
                                            <div style="flex-grow: 1;">
                                                <div class="issue-title">{{ $issue['title'] ?? 'Task' }}@if($photoCount > 0) ({{ $photoCount }} photo{{ $photoCount !== 1 ? 's' : '' }})@endif</div>
                                                <div class="issue-line">Logged: {{ $timestamp }}</div>
                                                @if(!empty($issue['description']))
                                                    <div class="issue-desc" style="margin-top: 4px;">{{ $issue['description'] }}</div>
                                                @endif
                                            </div>
                                        </div>
                                        @if($photoCount > 0)
                                            <div class="photo-thumb-grid" style="margin-top: 12px;">
                                                @foreach($issuePhotos as $photo)
                                                    @php
                                                        $photoUrl = $photo['url'] ?? '';
                                                    @endphp
                                                    @if($photoUrl)
                                                        @php
                                                            $photoCapturedAt = $photo['captured_at'] ?? null;
                                                            $photoTimestamp = $photoCapturedAt ? \App\Helpers\TimezoneHelper::toLocal($photoCapturedAt, $propertyTz)?->format('m/d/Y g:i:s A (T)') ?? 'No timestamp' : 'No timestamp';
                                                            $fullMeta = "Captured: {$photoTimestamp}<br>Cleaner: {$cleanerNameStr}";
                                                            if ($gpsStr) $fullMeta .= "<br>{$gpsStr}";
                                                            $fullTitle = $propNameStr . " &mdash; " . $location;
                                                        @endphp
                                                        <button type="button" class="photo-thumb js-photo-open" data-url="{{ $photoUrl }}" data-title="{{ $fullTitle }}" data-meta="{{ $fullMeta }}">
                                                            <img src="{{ $photoUrl }}" alt="Issue photo" loading="lazy">
                                                            <span class="timestamp">{{ $photoTimestamp }}</span>
                                                        </button>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    @endif
                </div>
                </div>
                </details>
            </section>

            <section id="compliance-checks" class="section">
                <div class="section-head">
                    <h2 class="section-title">Compliance Checks</h2>
                    <div class="section-sub">Required photos for verification tasks</div>
                </div>

                <details class="collapsible-section" style="border: 1px solid var(--line); border-radius: var(--radius-md); overflow: hidden; background: #ffffff;" {!! $isPdfView ? 'open' : '' !!}>
                    <summary>
                        Required Photos ({{ $complianceItems->count() }})
                        <span class="cs-chevron"><svg viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></span>
                    </summary>
                    <div class="cs-body">
                        @if($complianceItems->count() === 0)
                            <div class="empty">No required photos found.</div>
                        @else
                            <div class="pairs-grid">
                                @foreach($complianceItems as $item)
                                    @php
                                        $roomName = $item->room_id ? ($roomSections->firstWhere('id', $item->room_id)['name'] ?? 'Room #' . $item->room_id) : 'Property-level Tasks';
                                    @endphp
                                    
                                    @if($item->photos->isEmpty())
                                        <article class="pair-card">
                                            <div class="pair-slot" style="background: #f8f9fa; border: 1px dashed #dee2e6; display: flex; align-items: center; justify-content: center; color: var(--text-soft); font-size: 13px;">
                                                <div style="text-align: center;">
                                                    <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.5; margin-bottom: 4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                                    <br>Missing Photo
                                                </div>
                                            </div>
                                            <div class="pair-footer">
                                                <div class="pair-room">{{ $roomName }} - {{ $item->task?->name ?? 'Verify' }}</div>
                                                <div class="pair-time">
                                                    <span style="color: var(--danger);">Required Verification Missing</span>
                                                </div>
                                            </div>
                                        </article>
                                    @else
                                        @foreach($item->photos as $photo)
                                            @php
                                                $photoUrl = $photo->report_url ?? $photo->url ?? '';
                                                $localCapturedAt = $photo->captured_at ? \App\Helpers\TimezoneHelper::toLocal($photo->captured_at, $propertyTz) : null;
                                                $timestamp = $localCapturedAt ? $localCapturedAt->format('m/d/Y g:i:s A (T)') : 'No timestamp';
                                                $downloadUrl = $photo->download_url ?? $photoUrl;
                                                $fullMeta = "Captured: {$timestamp}<br>Cleaner: {$cleanerNameStr}";
                                                if ($gpsStr) $fullMeta .= "<br>{$gpsStr}";
                                                $fullTitle = $propNameStr . " &mdash; " . $roomName;
                                            @endphp
                                            @if($photoUrl)
                                                <article class="pair-card">
                                                    <button type="button" class="pair-slot js-photo-open" data-url="{{ $photoUrl }}" data-title="{{ $fullTitle }}" data-meta="{{ $fullMeta }}" data-download="{{ $downloadUrl }}">
                                                        <img src="{{ $photoUrl }}" alt="Required Photo" loading="lazy">
                                                    </button>
                                                    <div class="pair-footer">
                                                        <div class="pair-room">{{ $roomName }} - {{ $item->task?->name ?? 'Verify' }}</div>
                                                        <div class="pair-time">
                                                            <span>Required Verification</span>
                                                        </div>
                                                    </div>
                                                </article>
                                            @endif
                                        @endforeach
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </details>
            </section>

            <section id="gallery" class="section">
                <div class="section-head">
                    <h2 class="section-title">Completion Photos</h2>
                    @if(!$isPdfView && $hasPhotoZip)
                        <div class="actions no-pdf" style="order: 3;">
                            <a class="btn btn-primary" href="{{ $photosZipUrl }}">Download All Photos</a>
                        </div>
                    @else
                        <div class="section-sub">Photos grouped by room</div>
                    @endif
                </div>

                <details class="collapsible-section" style="border: 1px solid var(--line); border-radius: var(--radius-md); overflow: hidden; background: #ffffff;" {!! $isPdfView ? 'open' : '' !!}>
                    <summary>
                        View Photos ({{ $completionPhotosCount }})
                        <span class="cs-chevron"><svg viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></span>
                    </summary>
                    <div class="cs-body">
                        <div class="panel" style="border: none; box-shadow: none;">
                            @if($isPdfView)
                        <div class="table-wrap">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Gallery Metric</th>
                                        <th>Value</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="cell-key">Photos</td>
                                        <td>{{ $completionPhotosCount }}</td>
                                        <td>Captured during job completion</td>
                                    </tr>
                                    <tr>
                                        <td class="cell-key">Rooms With Photos</td>
                                        <td>{{ $roomsWithPhotos->count() }}</td>
                                        <td>Grouped by room</td>
                                    </tr>
                                    <tr>
                                        <td class="cell-key">Download Options</td>
                                        <td>{{ $hasPhotoZip ? 'Available' : 'None' }}</td>
                                        <td>All photos, per room, or individual image</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        @if($completionPhotos->count() > 0)
                            <div class="table-wrap table-gap">
                                <table class="report-table">
                                    <thead>
                                        <tr>
                                            <th>Room</th>
                                            <th>Photo</th>
                                            <th>Captured At</th>
                                            <th>Uploaded By</th>
                                            <th>Download</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($completionRooms as $roomGallery)
                                            @foreach($roomGallery['photos'] as $photo)
                                                @php
                                                    $pdfLocalCapturedAt = $photo['captured_at'] ? \App\Helpers\TimezoneHelper::toLocal($photo['captured_at'], $propertyTz) : null;
                                                    $timestamp = $pdfLocalCapturedAt ? $pdfLocalCapturedAt->format('m/d/Y g:i:s A (T)') : 'No timestamp';
                                                    $downloadUrl = $photo['download_url'] ?? $photo['url'];
                                                    $meta = "Captured: {$timestamp}<br>Cleaner: {$cleanerNameStr}";
                                                    if ($gpsStr) $meta .= "<br>{$gpsStr}";
                                                @endphp
                                                <tr>
                                                    <td>{{ $roomGallery['room_name'] }}</td>
                                                    <td>
                                                        <img class="table-thumb" src="{{ $photo['url'] }}" alt="Completion photo">
                                                    </td>
                                                    <td>{!! $meta !!}</td>
                                                    <td>{{ $photo['uploader'] ?? $preparedBy }}</td>
                                                    <td class="table-note">{{ $downloadUrl }}</td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @else
                        @if($completionPhotos->count() === 0)
                            <div class="empty">No completion photos were uploaded for this session.</div>
                        @else
                            <div class="gallery">
                                @foreach($completionRooms as $roomGallery)
                                    @php
                                        $roomIconKey = $roomIcon($roomGallery['room_name']);
                                    @endphp
                                    <article class="gallery-room" id="room-{{ $roomGallery['room_id'] }}">
                                        <div class="gallery-room-head">
                                            <div class="room-lbl">
                                                <span class="ic" aria-hidden="true">
                                                    @switch($roomIconKey)
                                                        @case('kitchen')
                                                            <svg viewBox="0 0 24 24"><path d="M5 3v8M5 11c-1.2 0-2 .8-2 2v8M5 11c1.2 0 2 .8 2 2v8M10 3v18M16 3l-2 8h4l-2-8zm0 8v10"/></svg>
                                                            @break
                                                        @case('bath')
                                                            <svg viewBox="0 0 24 24"><path d="M12 3.5S6 9.5 6 13a6 6 0 0 0 12 0c0-3.5-6-9.5-6-9.5z"/></svg>
                                                            @break
                                                        @case('bed')
                                                            <svg viewBox="0 0 24 24"><path d="M3 12h18v5H3z"/><path d="M5 12V9a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3"/><path d="M3 17v2M21 17v2"/></svg>
                                                            @break
                                                        @case('living')
                                                            <svg viewBox="0 0 24 24"><path d="M4 11h16a2 2 0 0 1 2 2v3H2v-3a2 2 0 0 1 2-2z"/><path d="M6 11V8a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v3"/><path d="M4 16v3M20 16v3"/></svg>
                                                            @break
                                                        @case('laundry')
                                                            <svg viewBox="0 0 24 24"><rect x="5" y="4" width="14" height="16" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M8 7h.01M11 7h.01"/></svg>
                                                            @break
                                                        @case('dining')
                                                            <svg viewBox="0 0 24 24"><path d="M7 3v8M5 3v8M7 7H5M6 11v10"/><path d="M14 3v8a2 2 0 0 0 4 0V3"/><path d="M16 11v10"/></svg>
                                                            @break
                                                        @case('hall')
                                                            <svg viewBox="0 0 24 24"><path d="M6 3h10a1 1 0 0 1 1 1v16H5V4a1 1 0 0 1 1-1z"/><path d="M10 12h.01"/></svg>
                                                            @break
                                                        @case('outdoor')
                                                            <svg viewBox="0 0 24 24"><path d="M19 3c-8 0-13 5-13 13 8 0 13-5 13-13z"/><path d="M8 14c2-2 4-3 7-4"/></svg>
                                                            @break
                                                        @case('property')
                                                            <svg viewBox="0 0 24 24"><path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-5h4v5"/></svg>
                                                            @break
                                                        @default
                                                            <svg viewBox="0 0 24 24"><path d="M12 21s6-5.1 6-10a6 6 0 1 0-12 0c0 4.9 6 10 6 10z"/><circle cx="12" cy="11" r="2.2"/></svg>
                                                    @endswitch
                                                </span>
                                                <span>{{ $roomGallery['room_name'] }} ({{ $roomGallery['photo_count'] }})</span>
                                            </div>
                                            <div class="actions no-pdf">
                                                @if($roomGallery['photo_count'] > 0)
                                                    <a class="btn" href="{{ $roomGallery['download_url'] }}">Download Room Photos</a>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="pairs-grid">
                                            @foreach($roomGallery['photos'] as $photo)
                                                @php
                                                    $localCapturedAt = $photo['captured_at'] ? \App\Helpers\TimezoneHelper::toLocal($photo['captured_at'], $propertyTz) : null;
                                                    $timestamp = $localCapturedAt ? $localCapturedAt->format('m/d/Y g:i:s A (T)') : 'No timestamp';
                                                    $downloadUrl = $photo['download_url'] ?? $photo['url'];
                                                    $fullMeta = "Captured: {$timestamp}<br>Cleaner: {$cleanerNameStr}";
                                                    if ($gpsStr) $fullMeta .= "<br>{$gpsStr}";
                                                    $fullTitle = $propNameStr . " &mdash; " . $roomGallery['room_name'];
                                                @endphp
                                                <article class="pair-card">
                                                    <button
                                                        type="button"
                                                        class="pair-slot js-photo-open"
                                                        data-url="{{ $photo['url'] }}"
                                                        data-title="{{ $fullTitle }}"
                                                        data-meta="{{ $fullMeta }}"
                                                        data-download="{{ $downloadUrl }}"
                                                    >
                                                        <img src="{{ $photo['url'] }}" alt="Completion photo" loading="lazy">
                                                        <span class="timestamp">{{ $timestamp }}</span>
                                                    </button>
                                                    <div class="pair-footer">
                                                        <div class="pair-room">{{ $fullTitle }}</div>
                                                        <div class="pair-time">
                                                            <span>{{ $photo['uploader'] ?? $preparedBy }}</span>
                                                        </div>
                                                        <div class="pair-actions no-pdf">
                                                            <button
                                                                type="button"
                                                                class="js-photo-open"
                                                                data-url="{{ $photo['url'] }}"
                                                                data-title="{{ $fullTitle }}"
                                                                data-meta="{{ $fullMeta }}"
                                                                data-download="{{ $downloadUrl }}"
                                                            >
                                                                View
                                                            </button>
                                                            <a href="{{ $downloadUrl }}" download>Download</a>
                                                        </div>
                                                    </div>
                                                </article>
                                            @endforeach
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                        @endif
                    </div>
                    </div>
                </details>
            </section>

            <section id="checklist" class="section">
                <div class="section-head">
                    <h2 class="section-title">Checklist Results</h2>
                    <div class="section-sub">Room-by-room checklist including inventory items</div>
                </div>

                <details class="collapsible-section" style="border: 1px solid var(--line); border-radius: var(--radius-md); overflow: hidden; background: #ffffff;" {!! $isPdfView ? 'open' : '' !!}>
                    <summary>
                        All Tasks ({{ $totalChecklist }} total, {{ $checkedChecklist }} completed)
                        <span class="cs-chevron"><svg viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></span>
                    </summary>
                <div class="cs-body">
                    <div class="cs-body">
                        @if($checklistSections->count() === 0 && (!isset($propertyItems) || $propertyItems->count() === 0))
                            <div class="empty">No checklist items were recorded for this session.</div>
                        @else
                            <div class="checklist-combined" style="margin-top: 16px;">
                                @if(isset($propertyItems) && $propertyItems->count() > 0)
                                    <div class="table-wrap table-gap" style="margin-bottom: 16px;">
                                        <table class="report-table">
                                            <thead>
                                                <tr><th colspan="2">Property-level Tasks</th></tr>
                                            </thead>
                                            <tbody>
                                                @foreach($propertyItems as $item)
                                                    <tr>
                                                        <td style="font-size: 10pt; color: {{ $item->checked ? 'var(--ok)' : 'var(--danger)' }};">
                                                            <span style="display: inline-block; width: 16px; font-weight: bold;">{!! $item->checked ? '&#10003;' : '&#10007;' !!}</span> {{ $item->task?->name ?? 'Task' }}
                                                        </td>
                                                        <td style="width: 220px; text-align: right; white-space: nowrap; padding-right: 28px; color: var(--text-soft);">
                                                            {{ $item->checked && $item->checked_at ? \App\Helpers\TimezoneHelper::toLocal($item->checked_at, $propertyTz)?->format('m/d/Y - g:i A (T)') ?? '--' : '--' }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif

                                @foreach($checklistSections as $section)
                                    @if(isset($section['items']) && $section['items']->count() > 0)
                                        <div class="table-wrap table-gap" style="margin-bottom: 16px;">
                                            <table class="report-table">
                                                <thead>
                                                    <tr><th colspan="2">{{ $section['name'] }}</th></tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($section['items'] as $item)
                                                        <tr>
                                                            <td style="font-size: 10pt; color: {{ $item->checked ? 'var(--ok)' : 'var(--danger)' }};">
                                                                <span style="display: inline-block; width: 16px; font-weight: bold;">{!! $item->checked ? '&#10003;' : '&#10007;' !!}</span> {{ $item->task?->name ?? 'Task' }}
                                                            </td>
                                                            <td style="width: 220px; text-align: right; white-space: nowrap; padding-right: 28px; color: var(--text-soft);">
                                                                {{ $item->checked && $item->checked_at ? \App\Helpers\TimezoneHelper::toLocal($item->checked_at, $propertyTz)?->format('m/d/Y - g:i A (T)') ?? '--' : '--' }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </details>
            </section>

            <section id="signoff" class="section">
                <div class="section-head">
                    <h2 class="section-title">Sign-off & Audit Trail</h2>
                    <div class="section-sub">Completion confirmation and event timeline</div>
                </div>

                @if($isPdfView)
                    <div class="table-wrap">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Sign-off Field</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="cell-key">Completed By</td>
                                    <td>{{ $preparedBy }}</td>
                                </tr>
                                <tr>
                                    <td class="cell-key">Verified By (Optional)</td>
                                    <td>{{ $session->owner?->name ?? 'Not assigned' }}</td>
                                </tr>
                                <tr>
                                    <td class="cell-key">Status</td>
                                    <td>{{ ucwords(str_replace('_', ' ', $session->status)) }}</td>
                                </tr>
                                <tr>
                                    <td class="cell-key">Report Generated</td>
                                    <td>{{ $generatedText }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-wrap table-gap">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Audit Event</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($timeline as $event)
                                    <tr>
                                        <td>{{ $event['at']->format('g:i A') }}</td>
                                        <td>{{ $event['label'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td>--:--</td>
                                        <td>No audit events available.</td>
                                    </tr>
                                @endforelse
                                <tr>
                                    <td>{{ $generatedAt->format('g:i A') }}</td>
                                    <td>Report generated and published</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @else
                    <article class="panel signoff-panel">
                        <div class="signoff-identity">
                            <div class="signoff-row">
                                <div class="signoff-key">Completed By</div>
                                <div class="signoff-value">{{ $preparedBy }}</div>
                            </div>

                            <div class="signoff-row">
                                <div class="signoff-key">Verified By (Optional)</div>
                                <div class="signoff-value-secondary">{{ $session->owner?->name ?? 'Not assigned' }}</div>
                            </div>

                            <div class="signoff-row">
                                <div class="signoff-key">Status</div>
                                <div class="signoff-value-secondary">{{ ucwords(str_replace('_', ' ', $session->status)) }}</div>
                            </div>

                            <div class="signoff-row">
                                <div class="signoff-key">Report Generated</div>
                                <div class="signoff-value-secondary">{{ $generatedText }}</div>
                            </div>
                        </div>

                        <div class="metric-label" style="margin-top: 20px;">Audit Log</div>
                        <div class="timeline">
                            @forelse($timeline as $event)
                                <div class="timeline-row">
                                    <div class="timeline-time">{{ $event['at']->format('g:i A') }}</div>
                                    <div class="timeline-text">{{ $event['label'] }}</div>
                                </div>
                            @empty
                                <div class="timeline-row">
                                    <div class="timeline-time">--:--</div>
                                    <div class="timeline-text">No audit events available.</div>
                                </div>
                            @endforelse

                            <div class="timeline-row">
                                <div class="timeline-time">{{ $generatedAt->format('g:i A') }}</div>
                                <div class="timeline-text">Report generated and published</div>
                            </div>
                        </div>
                    </article>
                @endif
            </section>
        </main>
    </div>

    <div id="lightbox" class="lightbox" aria-hidden="true">
        <button type="button" id="lightboxClose" class="lightbox-close" aria-label="Close">X</button>
        <button type="button" id="lightboxPrev" class="lightbox-nav lightbox-prev" aria-label="Previous">&#8249;</button>
        <button type="button" id="lightboxNext" class="lightbox-nav lightbox-next" aria-label="Next">&#8250;</button>
        <div class="lightbox-inner">
            <img id="lightboxImage" class="lightbox-image" src="" alt="Report photo preview">
            <div class="lightbox-meta">
                <span id="lightboxCaption"></span>
                <span id="lightboxCounter" class="lightbox-counter"></span>
                <a id="lightboxDownload" class="lightbox-download no-pdf" href="#" download>Download Image</a>
            </div>
        </div>
    </div>

    <script>
        function copyReportLink() {
            const url = @json($reportUrl);

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url);
                return;
            }

            const input = document.createElement('input');
            input.value = url;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        }

        const lightbox = document.getElementById('lightbox');
        const lightboxImage = document.getElementById('lightboxImage');
        const lightboxCaption = document.getElementById('lightboxCaption');
        const lightboxDownload = document.getElementById('lightboxDownload');
        const lightboxClose = document.getElementById('lightboxClose');
        const lightboxPrev = document.getElementById('lightboxPrev');
        const lightboxNext = document.getElementById('lightboxNext');
        const lightboxCounter = document.getElementById('lightboxCounter');
        const roomDownloadMenus = Array.from(document.querySelectorAll('.room-download-menu'));

        // Build gallery array from all photo-open buttons
        const galleryItems = [];
        document.querySelectorAll('.js-photo-open').forEach((el) => {
            const url = el.getAttribute('data-url');
            if (url) {
                galleryItems.push({
                    url: url,
                    title: el.getAttribute('data-title') || 'Photo',
                    meta: el.getAttribute('data-meta') || '',
                    download: el.getAttribute('data-download') || url,
                    el: el,
                });
            }
        });

        let currentGalleryIndex = 0;

        function showGalleryItem(index) {
            if (index < 0 || index >= galleryItems.length) return;
            currentGalleryIndex = index;
            const item = galleryItems[index];
            lightboxImage.src = item.url;
            lightboxCaption.innerHTML = '<strong>' + item.title + '</strong><br>' + item.meta;
            lightboxDownload.href = item.download;
            lightboxCounter.textContent = (index + 1) + ' / ' + galleryItems.length;
            lightboxPrev.style.display = index > 0 ? '' : 'none';
            lightboxNext.style.display = index < galleryItems.length - 1 ? '' : 'none';
        }

        function openLightbox(url, title, meta, downloadUrl) {
            // Find matching gallery index
            let idx = galleryItems.findIndex(g => g.url === url);
            if (idx === -1) idx = 0;
            showGalleryItem(idx);
            lightbox.classList.add('on');
            lightbox.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            lightbox.classList.remove('on');
            lightbox.setAttribute('aria-hidden', 'true');
            lightboxImage.src = '';
            lightboxDownload.href = '#';
            document.body.style.overflow = '';
        }

        lightboxPrev.addEventListener('click', (e) => { e.stopPropagation(); showGalleryItem(currentGalleryIndex - 1); });
        lightboxNext.addEventListener('click', (e) => { e.stopPropagation(); showGalleryItem(currentGalleryIndex + 1); });
        
        lightboxImage.style.cursor = 'zoom-in';
        lightboxImage.addEventListener('click', (e) => {
            e.stopPropagation();
            if (lightboxImage.src) {
                window.open(lightboxImage.src, '_blank');
            }
        });

        // Swipe support for mobile
        let touchStartX = 0;
        let touchStartY = 0;
        lightbox.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        }, { passive: true });

        lightbox.addEventListener('touchend', (e) => {
            const dx = e.changedTouches[0].screenX - touchStartX;
            const dy = e.changedTouches[0].screenY - touchStartY;
            if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 50) {
                if (dx < 0) { showGalleryItem(currentGalleryIndex + 1); }
                else { showGalleryItem(currentGalleryIndex - 1); }
            }
        }, { passive: true });

        document.querySelectorAll('.js-photo-open').forEach((el) => {
            el.addEventListener('click', () => {
                const url = el.getAttribute('data-url');
                const title = el.getAttribute('data-title') || 'Photo';
                const meta = el.getAttribute('data-meta') || '';
                const download = el.getAttribute('data-download') || url;

                if (!url) {
                    return;
                }

                openLightbox(url, title, meta, download);
            });
        });

        lightboxClose.addEventListener('click', closeLightbox);

        lightbox.addEventListener('click', (event) => {
            if (event.target === lightbox) {
                closeLightbox();
            }
        });

        document.addEventListener('click', (event) => {
            roomDownloadMenus.forEach((menu) => {
                if (!menu.open || menu.contains(event.target)) {
                    return;
                }

                menu.removeAttribute('open');
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                roomDownloadMenus.forEach((menu) => menu.removeAttribute('open'));
            }

            if (lightbox.classList.contains('on')) {
                if (event.key === 'Escape') { closeLightbox(); }
                if (event.key === 'ArrowLeft') { showGalleryItem(currentGalleryIndex - 1); }
                if (event.key === 'ArrowRight') { showGalleryItem(currentGalleryIndex + 1); }
            }
            
            const overrideModal = document.getElementById('override-modal');
            if (event.key === 'Escape' && overrideModal && overrideModal.style.display === 'flex') {
                overrideModal.style.display = 'none';
            }
        });
    </script>
    
    @if(!$isPdfView && auth()->check() && auth()->user()->hasAnyRole(['admin', 'owner', 'company']) && !$isComplete)
    <div id="override-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: #fff; padding: 24px; border-radius: 8px; max-width: 450px; width: 100%; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <h2 style="margin: 0 0 12px 0; font-size: 18px; color: var(--navy); font-weight: 600;">Manager Override - Close Session</h2>
            <p style="margin: 0 0 16px 0; font-size: 14px; color: var(--text-soft); line-height: 1.5;">This will forcefully mark the session as completed, bypass all workflow restrictions, and trigger completion notifications. Please provide a reason for the activity log.</p>
            <form method="post" action="{{ route('sessions.admin-close', $session) }}">
                @csrf
                <input type="text" name="note" required placeholder="e.g., Cleaner had to leave due to emergency" style="width: 100%; padding: 10px 12px; border: 1px solid var(--line-strong); border-radius: 6px; margin-bottom: 20px; font-family: inherit; font-size: 14px; box-sizing: border-box;">
                <div style="display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="button" class="btn" onclick="document.getElementById('override-modal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn" style="background: #ef4444; color: #fff; border-color: #ef4444;">Confirm Override</button>
                </div>
            </form>
        </div>
    </div>
    @endif
    
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof GLightbox !== 'undefined') {
            const lightbox = GLightbox({
                selector: '.gallery-lightbox'
            });
        }
    });
</script>
</body>
</html>
