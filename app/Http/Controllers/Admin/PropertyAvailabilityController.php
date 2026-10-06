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
 * Rates and restrictions are managed here ONLY for properties whose
 * rate_source is 'guesthub' (default 'external': tools like PriceLabs own
 * rates). They are never linked to booking fees or any guest charge.
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

        $roomTypeChanged = $property->channex_room_type_id !== $data['channex_room_type_id'];
        $property->update($data);
        if ($roomTypeChanged) {
            $property->forceFill(['channex_availability_seeded_at' => null])->save();
        }

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
        if ($property->channex_availability_seeded_at && ! request()->boolean('reseed')) {
            return back()->with('error', 'This property was already seeded to Channex. Make date changes in Channex. Use re-seed only if you are sure.');
        }

        if (! $property->airbnb_ical_url) {
            return back()->with('error', 'Set an Airbnb iCal URL for this property first.');
        }

        try {
            $ranges = $importer->fetchBlockedRanges($property->airbnb_ical_url);
            $statusMap = $importer->expandToStatusMap($ranges);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $today = now()->startOfDay();
        $to = $today->copy()->addDays(499); // 500 days, matches the Channex full-sync test

        $now = now();
        $rows = [];
        $cursor = $today->copy();

        while ($cursor->lte($to)) {
            $key = $cursor->format('Y-m-d');
            $status = $statusMap[$key] ?? 'available';
            $rows[] = [
                'property_id' => $property->id,
                'date' => $key,
                'is_available' => $status === 'available',
                'status' => $status,
                'source' => 'ical',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $cursor->addDay();
        }

        $blocked = collect($rows)->where('is_available', false)->count();

        // Guard: if nearly everything is blocked, Airbnb may be blocking dates
        // because of $0 rates. Seeding that would make the blocks permanent.
        if ($blocked / max(1, count($rows)) > 0.8 && ! request()->boolean('confirm_high_block')) {
            return back()->with('error', $blocked.' of '.count($rows).' dates look blocked. If Airbnb is blocking because of $0 rates, importing would lock that in. Check the feed first.');
        }

        DB::transaction(function () use ($property, $rows, $today) {
            $property->availabilities()->where('date', '<', $today)->delete();
            PropertyAvailability::upsert($rows, ['property_id', 'date'], ['is_available', 'status', 'source', 'updated_at']);
        });

        $blockedCount = $blocked;
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
        if ($property->channex_availability_seeded_at && ! request()->boolean('reseed')) {
            return back()->with('error', 'This property was already seeded to Channex. Make date changes in Channex. Use re-seed only if you are sure.');
        }

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

        $property->forceFill(['channex_availability_seeded_at' => now()])->save();

        ActivityLog::record('property_channex_pushed', "{$property->name}: pushed availability to Channex for {$rows->count()} date(s).", 'properties', $property);

        return back()->with('success', 'Pushed to Channex successfully. It may take a few minutes to reflect on Airbnb.');
    }

    /**
     * Rate source + rate plan. 'external' (default) means a tool such as
     * PriceLabs owns rates and Guesthub never sends them.
     */
    public function saveRateSettings(Request $request, Property $property)
    {
        $data = $request->validate([
            'rate_source' => ['required', 'in:guesthub,external'],
            'channex_rate_plan_id' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($data['channex_rate_plan_id'])) {
            unset($data['channex_rate_plan_id']);
        }

        $planId = $data['channex_rate_plan_id'] ?? $property->channex_rate_plan_id;

        if ($data['rate_source'] === 'guesthub' && (! $planId || ! $property->channex_room_type_id)) {
            return back()->with('error', 'Map the Channex room type and choose a rate plan before letting Guesthub manage rates.');
        }

        $property->update($data);

        ActivityLog::record('property_rate_settings_updated', "{$property->name}: rate source set to {$property->rate_source}.", 'properties', $property);

        return back()->with('success', 'Rate settings saved.');
    }

    /** Lists the rate plans Channex has for this property's mapped room type. */
    public function fetchRatePlans(Property $property, PmsProviderInterface $provider)
    {
        if (! $property->channex_property_id || ! $property->channex_room_type_id) {
            return back()->with('error', 'Set the Channex property ID and map the room type first.');
        }

        if (! method_exists($provider, 'getRatePlans')) {
            return back()->with('error', 'The current PMS provider cannot list rate plans.');
        }

        $options = collect($provider->getRatePlans($property->channex_property_id))
            ->filter(fn ($o) => empty($o['room_type_id']) || $o['room_type_id'] === $property->channex_room_type_id)
            ->values()
            ->all();

        if (empty($options)) {
            return back()->with('error', 'Channex returned no rate plans for this room type. Check the Channex side, and laravel.log.');
        }

        return back()->with('ratePlanOptions', $options);
    }

    /**
     * Applies availability and/or rate and restriction values to a date
     * range. Blank fields are left unchanged. Rates and restrictions are
     * nightly values only; nothing here reads or writes booking fees.
     */
    public function updateRange(Request $request, Property $property, \App\Services\Pms\AvailabilityLedger $ledger)
    {
        $data = $request->validate([
            'date_from' => ['required', 'date', 'after_or_equal:today'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'rate' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
            'min_stay_arrival' => ['nullable', 'integer', 'min:1', 'max:365'],
            'min_stay_through' => ['nullable', 'integer', 'min:1', 'max:365'],
            'max_stay' => ['nullable', 'integer', 'min:1', 'max:365'],
            'stop_sell' => ['nullable', 'in:0,1'],
            'closed_to_arrival' => ['nullable', 'in:0,1'],
            'closed_to_departure' => ['nullable', 'in:0,1'],
            'availability' => ['nullable', 'in:open,block'],
        ]);

        $from = \Carbon\Carbon::parse($data['date_from'])->startOfDay();
        $to = \Carbon\Carbon::parse($data['date_to'])->startOfDay();

        if ($from->diffInDays($to) + 1 > 500) {
            return back()->withInput()->with('error', 'Choose a range of 500 days or fewer.');
        }

        $values = [];

        foreach (['rate'] as $f) {
            if (filled($data[$f] ?? null)) {
                $values[$f] = round((float) $data[$f], 2);
            }
        }
        foreach (['min_stay_arrival', 'min_stay_through', 'max_stay'] as $f) {
            if (filled($data[$f] ?? null)) {
                $values[$f] = (int) $data[$f];
            }
        }
        foreach (['stop_sell', 'closed_to_arrival', 'closed_to_departure'] as $f) {
            if (filled($data[$f] ?? null)) {
                $values[$f] = $data[$f] === '1';
            }
        }

        if ($values && ($property->rate_source !== 'guesthub' || ! $property->channex_rate_plan_id)) {
            return back()->withInput()->with('error', 'Set the rate source to Guesthub and choose a rate plan before changing rates or restrictions.');
        }

        if (($data['availability'] ?? null) === 'block') {
            $values['status'] = 'blocked';
        } elseif (($data['availability'] ?? null) === 'open') {
            $values['status'] = 'available';
        }

        if (! $values) {
            return back()->withInput()->with('error', 'Nothing to change. Fill in at least one field.');
        }

        $dates = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $dates[] = $d->format('Y-m-d');
        }

        $ledger->setDates($property, $dates, $values, 'manual');

        // A night still covered by an active booking must stay closed.
        $ledger->syncNights($property->id, $from->toDateString(), $to->copy()->addDay()->toDateString());

        ActivityLog::record('property_availability_edited', "{$property->name}: edited ".count($dates).' date(s) ('.$from->format('M j').' to '.$to->format('M j, Y').').', 'properties', $property);

        return back()->with('success', count($dates).' date(s) updated. Changes go to Channex within about a minute.');
    }

    /**
     * Sends the next 500 days of availability and rates/restrictions to
     * Channex now (one call per kind), instead of waiting on the outbox.
     */
    public function fullSync(Property $property, \App\Services\Pms\ChannexOutboxSender $sender)
    {
        $r = $sender->fullSync($property);

        $summary = "{$r['availability_dates']} availability date(s) and {$r['restriction_dates']} rate/restriction date(s) sent in {$r['calls']} call(s).";
        $notes = $r['messages'] ? ' ' . implode(' ', $r['messages']) : '';

        ActivityLog::record(
            $r['failed'] ? 'property_channex_full_sync_failed' : 'property_channex_full_sync',
            "{$property->name}: full sync. {$summary}",
            'properties',
            $property
        );

        if ($r['failed']) {
            return back()->with('error', 'Full sync had a problem. ' . $summary . $notes);
        }

        return back()->with('success', 'Full sync done. ' . $summary . $notes);
    }
}
