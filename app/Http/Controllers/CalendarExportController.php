<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\FeatureGate;
use App\Services\IcalExportService;
use Illuminate\Http\Request;

class CalendarExportController extends Controller
{
    public function __invoke(Request $request, string $token, IcalExportService $calendar)
    {
        $for = $request->query('for');
        abort_if($for !== null && ! in_array($for, ['airbnb', 'vrbo', 'other'], true), 400);

        $property = Property::query()
            ->where('ical_export_token', $token)
            ->with('owner')
            ->firstOrFail();
        abort_unless($property->active && $property->owner
            && FeatureGate::enabled($property->owner, 'calendar_export'), 404);

        return response($calendar->export($property, $for), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
