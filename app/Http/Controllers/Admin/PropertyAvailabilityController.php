<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Property;
use App\Models\PropertyAvailability;
use App\Services\Pms\AirbnbIcalImporter;
use App\Services\Pms\PmsProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin page for a single property's Airbnb availability sync: import
 * blocked dates from Airbnb's iCal export, map the property to a Channex
 * room type, and push the full imported dataset to Channex (which
 * propagates to Airbnb and other connected OTAs).
 *
 * Deliberately does NOT manage rates -- rate tools (e.g. PriceLabs) push
 * rates directly into Channex, so Guesthub has no rate-management role.
 * Deliberately has NO manual editing of dates or manual entry of Channex
 * IDs -- the only inputs are the Airbnb iCal URL and the Channex property
 * ID (set on the main property form); everything else is fetched or
 * derived automatically.
 */
class PropertyAvailabilityController extends Controller
{
    /**
     * Shows the property's Channex mapping status, iCal URL, and a
     * read-only preview of the currently imported availability data.
     */
    public function index(Property $property)
    {
        $availabilities = $property->availabilities()
            ->orderBy('date')
            ->get();

        return view('admin.properties.availability', [
            'property' => $property,
            'availabilities' => $availabilities,
            'blockedCount' => $availabilities->where('is_available', false)->count(),
            'isMapped' => (bool) $property->channex_room_type_id,
        ]);
    }

    /**
     * Fetches room types Channex has on file for this property (via its
     * existing Airbnb connection), so the admin can pick the correct one.
     * No manual-entry fallback: if Channex returns nothing, the mapping
     * simply can't be completed yet (the Channex-side setup is incomplete).
     */
    public function fetchMapping(Property $property, PmsProviderInterface $provider)
    {
        if (! $property->channex_property_id) {
            return back()->with('error', 'Set the Channex Property ID for this property first (on the main property form).');
        }

        $options = $provider->getRoomTypes($property->channex_property_id);

        if (empty($options)) {
            return back()->with('error', 'Channex returned no room types for this property yet. Confirm the property/room type is set up on the Channex side.');
        }

        return back()->with('mappingOptions', $options);
    }

    /**
     * Saves the admin's chosen room_type_id onto the property. Only ever
     * called with a value that came from fetchMapping()'s dropdown -- there
     * is no manual entry path.
     */
    public function saveMapping(Request $request, Property $property)
    {
        $data = $request->validate([
            'channex_room_type_id' => ['required', 'string', 'max:255'],
        ]);

        $property->update($data);

        ActivityLog::record('property_channex_mapping_updated', "{$property->name}'s Channex room type mapping was updated.", 'properties', $property);

        return back()->with('success', 'Channex mapping saved.');
    }

    /**
     * Saves the admin's Airbnb iCal export URL onto the property.
     */
    public function saveIcalUrl(Request $request, Property $property)
    {
        $data = $request->validate([
            'airbnb_ical_url' => ['required', 'url', 'max:2048'],
        ]);

        $property->update($data);

        ActivityLog::record('property_ical_url_updated', "{$property->name}'s Airbnb iCal URL was updated.", 'properties', $property);

        return back()->with('success', 'Airbnb iCal URL saved.');
    }

    /**
     * The "Import from Airbnb" button. Fetches the property's stored iCal
     * URL, parses blocked dates, and REPLACES PropertyAvailability's rows
     * for this property with exactly what Airbnb currently reports: every
     * date in the feed's range is written, either available or blocked, so
     * the imported dataset always exactly mirrors Airbnb's current state
     * rather than accumulating stale rows from earlier imports.
     */
    /**
     * Default forward window (days from today) used when the Airbnb feed
     * has zero blocked dates -- with no blocks to anchor a range on, we
     * still need *some* range to mark explicitly available, so every date
     * has a real row rather than an implicit, unpushed default.
     */
    private const DEFAULT_IMPORT_WINDOW_DAYS = 365;

    public function importIcal(Property $property, AirbnbIcalImporter $importer)
    {
        if (! $property->airbnb_ical_url) {
            return back()->with('error', 'Set an Airbnb iCal URL for this property first.');
        }

        try {
            $ranges = $importer->fetchBlockedRanges($property->airbnb_ical_url);
            $blockedDates = collect($importer->expandToDateList($ranges));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $today = now()->startOfDay();
        $blockedSet = $blockedDates->flip(); // for O(1) lookups below

        // The import must cover every date through the furthest one Airbnb
        // mentioned -- otherwise a date that gets unblocked on Airbnb later
        // has nothing to "clear" it here, since iCal only ever lists
        // blocks, never explicit availability (see prior discussion). If
        // there are no blocks at all, fall back to a fixed forward window
        // so newly-opened calendars still get a real, pushable dataset.
        $to = $blockedDates->isNotEmpty()
            ? \Carbon\Carbon::parse($blockedDates->max())
            : $today->copy()->addDays(self::DEFAULT_IMPORT_WINDOW_DAYS);

        $now = now();
        $rows = [];
        $cursor = $today->copy();

        while ($cursor->lte($to)) {
            $key = $cursor->format('Y-m-d');
            $rows[] = [
                'property_id' => $property->id,
                'date' => $key,
                'is_available' => ! $blockedSet->has($key),
                'source' => 'ical',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $cursor->addDay();
        }

        DB::transaction(function () use ($property, $rows) {
            // Full replace: every prior row for this property is gone,
            // replaced by an explicit true/false for every date in the
            // computed range -- never a partial/blocked-only write.
            $property->availabilities()->delete();
            PropertyAvailability::insert($rows);
        });

        $blockedCount = $blockedDates->count();
        $totalCount = count($rows);

        ActivityLog::record('property_ical_imported', "{$property->name}: imported {$totalCount} date(s) from Airbnb iCal ({$blockedCount} blocked).", 'properties', $property);

        return back()->with('success', "{$totalCount} date(s) imported from Airbnb ({$blockedCount} blocked, ".($totalCount - $blockedCount)." available). Review below, then push to Channex when ready.");
    }

    /**
     * Pushes every currently-imported PropertyAvailability row for this
     * property to Channex, exactly as imported -- no date range selection,
     * no partial push. Requires channex_room_type_id to be mapped first.
     */
    public function pushToChannex(Property $property, PmsProviderInterface $provider)
    {
        if (! $property->channex_room_type_id) {
            return back()->with('error', 'Set the Channex room type mapping for this property first.');
        }

        $rows = $property->availabilities()->get();

        if ($rows->isEmpty()) {
            return back()->with('error', 'No availability data to push yet. Import from Airbnb first.');
        }

        $availabilityMap = $rows->mapWithKeys(fn ($row) => [$row->date->format('Y-m-d') => $row->is_available])->all();

        $ok = $provider->pushAvailability($property->channex_property_id, $property->channex_room_type_id, $availabilityMap);

        if (! $ok) {
            ActivityLog::record('property_channex_push_failed', "{$property->name}: push to Channex failed.", 'properties', $property);

            return back()->with('error', 'Channex rejected the update. Check the logs for details.');
        }

        ActivityLog::record('property_channex_pushed', "{$property->name}: pushed availability to Channex for {$rows->count()} date(s).", 'properties', $property);

        return back()->with('success', 'Pushed to Channex successfully. It may take a few minutes to reflect on Airbnb.');
    }
}
