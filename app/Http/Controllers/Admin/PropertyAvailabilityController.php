<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ChannexOutbox;
use App\Models\Property;
use App\Models\PropertyAvailability;
use App\Services\FeatureGate;
use App\Services\Pms\AirbnbIcalImporter;
use App\Services\Pms\AvailabilityLedger;
use App\Services\Pms\ChannexOutboxSender;
use App\Services\Pms\PmsProviderInterface;
use App\Services\Pms\PriceLabsRateSource;
use App\Services\Pms\PriceLabsSync;
use App\Services\Pms\WheelhouseApiClient;
use App\Services\Pms\WheelhouseApiException;
use App\Services\Pms\WheelhouseConnectionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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
            'pendingCount' => ChannexOutbox::where('property_id', $property->id)->where('attempts', '<', 10)->count(),
            'failedCount' => ChannexOutbox::where('property_id', $property->id)->where('attempts', '>=', 10)->count(),
            'lastOutboxError' => ChannexOutbox::where('property_id', $property->id)->whereNotNull('last_error')->latest('updated_at')->value('last_error'),
            'priceLabsKeySet' => (bool) ($property->owner?->pricelabs_api_key ?: config('services.pricelabs.api_key')),
            'priceLabsHostKeySet' => (bool) $property->owner?->pricelabs_api_key,
            'priceLabsPlatformKeySet' => (bool) config('services.pricelabs.api_key'),
            'wheelhousePartnerKeySet' => (bool) config('services.wheelhouse.partner_api_key'),
            'wheelhouseUserKeySet' => (bool) $property->owner?->wheelhouse_user_api_key,
            'pushLog' => DB::table('channex_push_logs')
                ->where('channex_property_id', (string) $property->channex_property_id)
                ->latest('id')->limit(30)->get(),
            'calendarExportEnabled' => $property->owner && FeatureGate::enabled($property->owner, 'calendar_export'),
            'calendarExportUrl' => $property->ical_export_token
                ? route('ical.export', ['token' => $property->ical_export_token])
                : null,
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

        ActivityLog::record('property_ical_url_updated', "{$property->name}'s calendar link was updated.", 'properties', $property);

        return back()->with('success', 'Calendar link saved.');
    }

    public function savePriceLabs(Request $request, Property $property)
    {
        $data = $request->validate([
            'pricelabs_selection' => ['required', 'string', 'max:160'],
        ]);
        [$listingId, $pms] = array_pad(explode('|', $data['pricelabs_selection'], 2), 2, '');
        if ($listingId === '' || $pms === '' || strlen($listingId) > 100 || strlen($pms) > 50) {
            return back()->with('error', 'Choose a valid PriceLabs listing.');
        }
        $source = app(PriceLabsRateSource::class);
        $key = $source->apiKeyFor($property);
        if (! $key) {
            return back()->with('error', 'Verify a PriceLabs API key before choosing a listing.');
        }

        try {
            $listings = $source->listings($key);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $isValidListing = collect($listings)->contains(
            fn (array $listing) => $listing['id'] === $listingId
                && $listing['pms'] === $pms
        );
        if (! $isValidListing) {
            return back()->with('error', 'Choose a listing returned by PriceLabs for this API key.');
        }

        $property->forceFill([
            'pricelabs_listing_id' => $listingId,
            'pricelabs_pms' => $pms,
            'pricelabs_last_refreshed_at' => null, // so the next pull is not skipped as "unchanged"
        ])->save();

        ActivityLog::record('property_pricelabs_linked', "{$property->name}: PriceLabs listing linked.", 'properties', $property);

        return back()->with('success', 'PriceLabs listing saved. Use "Pull rates now" to test it.');
    }

    public function verifyPriceLabsKey(Request $request, Property $property, PriceLabsRateSource $source)
    {
        $data = $request->validate([
            'pricelabs_api_key' => ['nullable', 'string', 'max:500'],
        ]);
        $owner = $property->owner;
        if (! $owner) {
            return back()->with('error', 'Assign this property to a host before configuring PriceLabs.');
        }

        $submittedKey = trim((string) ($data['pricelabs_api_key'] ?? ''));
        $key = $submittedKey ?: $source->apiKeyFor($property);
        if (! $key) {
            return back()->with('error', 'Enter a PriceLabs API key to verify it.');
        }

        try {
            $listings = $source->listings($key);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($submittedKey !== '') {
            $owner->forceFill(['pricelabs_api_key' => $submittedKey])->save();
        }

        return back()
            ->with('success', 'PriceLabs API key verified. Choose the listing for this property.')
            ->with('priceLabsListings', $listings);
    }

    public function releaseManualRates(Property $property)
    {
        $n = $property->availabilities()
            ->where('rates_source', 'manual')
            ->update(['rates_source' => null]);

        if ($property->pricing_provider === 'pricelabs') {
            $property->forceFill(['pricelabs_last_refreshed_at' => null])->save();
        }

        return back()->with('success', $n
            ? "Released {$n} night(s) to ".($property->pricing_provider ?: 'the listing base rate').'. Pull provider rates to refresh them.'
            : 'No manually set nights to release.');
    }

    public function retryFailed(Property $property)
    {
        $n = ChannexOutbox::where('property_id', $property->id)
            ->where('attempts', '>=', 10)
            ->update(['attempts' => 0, 'next_attempt_at' => now(), 'last_error' => null]);

        return back()->with('success', $n
            ? "Retrying {$n} failed change(s). They go out within about a minute."
            : 'No failed changes to retry.');
    }

    public function saveWindow(Request $request, Property $property)
    {
        $data = $request->validate([
            'close_ahead_days' => ['nullable', 'integer', 'min:1', 'max:499'],
        ]);

        $property->forceFill(['close_ahead_days' => $data['close_ahead_days'] ?? null])->save();

        Artisan::call('availability:apply-window', ['--property' => $property->id]);

        ActivityLog::record('property_window_updated', "{$property->name}: booking window set to ".($property->close_ahead_days ? $property->close_ahead_days.' days' : 'off').'.', 'properties', $property);

        return back()->with('success', $property->close_ahead_days
            ? "Dates more than {$property->close_ahead_days} days ahead are now closed. Dates you open by hand stay open."
            : 'Booking window turned off. Dates the window had closed are open again.');
    }

    public function syncPriceLabs(Property $property, PriceLabsSync $sync)
    {
        $r = $sync->sync($property, true);

        return back()->with(
            in_array($r['status'], ['ok', 'unchanged'], true) ? 'success' : 'error',
            ucfirst((string) $property->pricing_provider).': '.$r['message'].($r['status'] === 'ok' && $property->channex_rate_plan_id ? ' They go to Channex within about a minute.' : '')
        );
    }

    public function saveWheelhouseKey(Request $request, Property $property, WheelhouseApiClient $client)
    {
        $data = $request->validate([
            'wheelhouse_user_api_key' => ['required', 'string', 'max:500'],
        ]);
        $owner = $property->owner;
        if (! $owner) {
            return back()->with('error', 'Assign this property to a host before configuring Wheelhouse.');
        }

        try {
            $listings = $client->listings(trim($data['wheelhouse_user_api_key']));
        } catch (WheelhouseApiException $exception) {
            return back()->with('error', 'Wheelhouse key verification failed: '.$exception->getMessage());
        }

        $owner->forceFill([
            'wheelhouse_user_api_key' => trim($data['wheelhouse_user_api_key']),
            'wheelhouse_integrators_user_id' => null,
        ])->save();

        return back()
            ->with('success', 'Wheelhouse API key verified. Choose a listing for this property.')
            ->with('wheelhouseListings', $this->wheelhouseListingOptions($listings));
    }

    public function saveWheelhouseListing(Request $request, Property $property, WheelhouseApiClient $client)
    {
        $data = $request->validate([
            'wheelhouse_selection' => ['required', 'string', 'max:220'],
        ]);
        [$listingId, $channel] = array_pad(explode('|', $data['wheelhouse_selection'], 2), 2, '');
        if ($listingId === '' || $channel === '' || strlen($listingId) > 160 || strlen($channel) > 50) {
            return back()->with('error', 'Choose a valid Wheelhouse listing.');
        }
        $key = $property->owner?->wheelhouse_user_api_key;
        if (! $key) {
            return back()->with('error', 'Verify the host’s Wheelhouse API key first.');
        }

        try {
            $listings = $this->wheelhouseListingOptions($client->listings($key));
        } catch (WheelhouseApiException $exception) {
            return back()->with('error', 'Wheelhouse listing lookup failed: '.$exception->getMessage());
        }

        $selected = collect($listings)->contains(
            fn (array $listing) => $listing['id'] === $listingId
                && $listing['channel'] === $channel
        );
        if (! $selected) {
            return back()->with('error', 'Choose a listing returned by Wheelhouse for this host.');
        }

        $property->forceFill([
            'wheelhouse_listing_id' => $listingId,
            'wheelhouse_channel' => $channel,
        ])->save();

        return back()->with('success', 'Wheelhouse listing saved.');
    }

    public function connectWheelhouse(Property $property, WheelhouseConnectionService $connection)
    {
        try {
            $connection->connectIntegratorListing($property);
        } catch (WheelhouseApiException $exception) {
            return back()->with('error', 'Wheelhouse connection failed: '.$exception->getMessage());
        }

        return back()->with('success', 'GuestHub registered the host and created or updated the Wheelhouse listing.');
    }

    public function savePricingProvider(Request $request, Property $property, AvailabilityLedger $ledger)
    {
        $data = $request->validate([
            'pricing_provider' => ['nullable', 'in:pricelabs,wheelhouse'],
        ]);
        $provider = $data['pricing_provider'] ?? null;

        if ($provider === 'pricelabs' && (! $property->pricelabs_listing_id || ! $property->pricelabs_pms)) {
            return back()->with('error', 'Verify a PriceLabs key and choose a listing first.');
        }
        if ($provider === 'pricelabs' && ! app(PriceLabsRateSource::class)->apiKeyFor($property)) {
            return back()->with('error', 'Configure a PriceLabs API key before activating PriceLabs.');
        }
        if ($provider === 'wheelhouse' && ! $property->wheelhouse_listing_id) {
            return back()->with('error', 'Connect Wheelhouse and choose or create a listing first.');
        }
        if ($provider === 'wheelhouse' && (! $property->owner?->wheelhouse_user_api_key || ! config('services.wheelhouse.partner_api_key'))) {
            return back()->with('error', 'Configure the Wheelhouse partner key and host API key before activating Wheelhouse.');
        }

        $previous = $property->pricing_provider;
        if ($previous === $provider) {
            return back()->with('success', 'Pricing provider is already set to '.($provider ?: 'base rate').'.');
        }

        DB::transaction(function () use ($property, $previous, $provider, $ledger): void {
            if ($previous && $previous !== $provider) {
                $ledger->clearProviderRates($property, $previous);
            }
            $property->forceFill(['pricing_provider' => $provider])->save();
        });

        ActivityLog::record(
            'property_pricing_provider_changed',
            "{$property->name}: pricing provider changed from ".($previous ?: 'base rate').' to '.($provider ?: 'base rate').'; prior provider rates were cleared.',
            'properties',
            $property,
        );

        return back()->with('success', 'Pricing provider updated. Previous provider rates were cleared; quotes use the new provider’s rates when available, otherwise the base rate.');
    }

    /**
     * @return array<int,array{id:string,channel:string,title:string}>
     */
    private function wheelhouseListingOptions(array $payload): array
    {
        $items = $payload['listings'] ?? $payload['data'] ?? $payload;
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => [
                'id' => (string) ($item['id'] ?? ''),
                'channel' => (string) ($item['channel'] ?? ''),
                'title' => (string) ($item['title'] ?? $item['nickname'] ?? $item['id'] ?? ''),
            ])
            ->filter(fn (array $item) => $item['id'] !== '' && $item['channel'] !== '')
            ->values()
            ->all();
    }

    /**
     * The "Import from the calendar link" button. Fetches the property's stored iCal
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
            return back()->with('error', 'Set a calendar link for this property first.');
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
            return back()->with('error', $blocked.' of '.count($rows).' dates look blocked. If the calendar is blocking because of $0 rates, importing would lock that in. Check the feed first.');
        }

        DB::transaction(function () use ($property, $rows, $today) {
            $property->availabilities()->where('date', '<', $today)->delete();
            PropertyAvailability::upsert($rows, ['property_id', 'date'], ['is_available', 'status', 'source', 'updated_at']);
        });

        $blockedCount = $blocked;
        $totalCount = count($rows);

        ActivityLog::record('property_ical_imported', "{$property->name}: imported {$totalCount} date(s) from the calendar link ({$blockedCount} blocked).", 'properties', $property);

        return back()->with('success', "{$totalCount} date(s) imported from the calendar link ({$blockedCount} blocked, ".($totalCount - $blockedCount).' available). Review below, then push to Channex when ready.');
    }

    /**
     * Compares the property's Airbnb calendar link with Guesthub's own held nights.
     * Report only, unless "apply" is posted: then dates that Airbnb holds and
     * Guesthub has open (or no row for) are closed here. Rows Guesthub already
     * holds are never touched.
     */
    public function compareIcal(Request $request, Property $property, AirbnbIcalImporter $importer, AvailabilityLedger $ledger)
    {
        if (! $property->airbnb_ical_url) {
            return back()->with('error', 'Set a calendar link for this property first.');
        }

        try {
            $map = $importer->expandToStatusMap($importer->fetchBlockedRanges($property->airbnb_ical_url));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $from = now()->startOfDay()->format('Y-m-d');
        $to = now()->startOfDay()->addDays(499)->format('Y-m-d');
        $map = array_filter($map, fn ($status, $date) => $date >= $from && $date <= $to, ARRAY_FILTER_USE_BOTH);

        $held = [];
        foreach ($property->availabilities()->whereBetween('date', [$from, $to])->get() as $row) {
            if (! $row->is_available) {
                $held[$row->date->format('Y-m-d')] = $row->source;
            }
        }

        $match = 0;
        $airbnbBooked = [];
        $airbnbBlocked = [];
        $guesthubOnly = [];
        foreach ($map as $date => $status) {
            if (isset($held[$date])) {
                $match++;
            } elseif ($status === 'booked') {
                $airbnbBooked[] = $date;
            } else {
                $airbnbBlocked[] = $date;
            }
        }
        foreach ($held as $date => $source) {
            if (! isset($map[$date])) {
                $guesthubOnly[] = $date;
            }
        }
        sort($airbnbBooked);
        sort($airbnbBlocked);
        sort($guesthubOnly);

        $applied = null;
        if ($request->boolean('apply')) {
            if ($property->channex_availability_seeded_at) {
                return back()->with('error', 'This property is seeded to Channex. Make date changes in Channex.');
            }
            if (count($map) / 500 > 0.8 && ! $request->boolean('confirm_high_block')) {
                return back()->with('error', count($map).' of 500 dates look blocked. Check the feed first.');
            }
            $ledger->setDates($property, $airbnbBooked, ['status' => 'booked'], 'ical');
            $ledger->setDates($property, $airbnbBlocked, ['status' => 'blocked'], 'ical');
            $applied = count($airbnbBooked) + count($airbnbBlocked);
            ActivityLog::record('property_ical_gaps_closed', "{$property->name}: closed {$applied} date(s) held on the calendar link but open here.", 'properties', $property);
        }

        return back()->with('ical_compare', [
            'match' => $match,
            'airbnb_only_booked' => $this->dateRanges($airbnbBooked),
            'airbnb_only_blocked' => $this->dateRanges($airbnbBlocked),
            'guesthub_only' => $this->dateRanges($guesthubOnly),
            'applied' => $applied,
        ]);
    }

    /** @param array<int,string> $dates sorted Y-m-d @return array<int,string> */
    private function dateRanges(array $dates): array
    {
        $out = [];
        $start = $prev = null;
        foreach ($dates as $d) {
            if ($start !== null && $d === date('Y-m-d', strtotime($prev.' +1 day'))) {
                $prev = $d;
                continue;
            }
            if ($start !== null) {
                $out[] = $start === $prev ? $start : "{$start} to {$prev}";
            }
            $start = $prev = $d;
        }
        if ($start !== null) {
            $out[] = $start === $prev ? $start : "{$start} to {$prev}";
        }

        return $out;
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
            return back()->with('error', 'No availability data to push yet. Import from the calendar link first.');
        }

        $availabilityMap = $rows->mapWithKeys(fn ($row) => [$row->date->format('Y-m-d') => $row->is_available])->all();

        $ok = $provider->pushAvailability($property->channex_property_id, $property->channex_room_type_id, $availabilityMap);

        if (! $ok) {
            ActivityLog::record('property_channex_push_failed', "{$property->name}: push to Channex failed.", 'properties', $property);

            return back()->with('error', 'Channex rejected the update. Check the logs for details.');
        }

        $property->forceFill(['channex_availability_seeded_at' => now()])->save();

        ActivityLog::record('property_channex_pushed', "{$property->name}: pushed availability to Channex for {$rows->count()} date(s).", 'properties', $property);

        return back()->with('success', 'Pushed to Channex successfully. It may take a few minutes to reflect on your connected channels.');
    }

    /**
     * Rate source + rate plan. 'external' (default) means a tool such as
     * PriceLabs owns rates and Guesthub never sends them.
     */
    public function saveRateSettings(Request $request, Property $property)
    {
        $data = $request->validate([
            'channex_rate_plan_id' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($data['channex_rate_plan_id'])) {
            unset($data['channex_rate_plan_id']);
        }

        $planId = $data['channex_rate_plan_id'] ?? $property->channex_rate_plan_id;

        if (! $planId || ! $property->channex_room_type_id) {
            return back()->with('error', 'Map the Channex room type and choose a rate plan first.');
        }

        $property->update($data + ['rate_source' => 'guesthub']);

        ActivityLog::record('property_rate_settings_updated', "{$property->name}: rate plan saved.", 'properties', $property);

        return back()->with('success', 'Rate plan saved.');
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
    public function updateRange(Request $request, Property $property, AvailabilityLedger $ledger)
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

        $from = Carbon::parse($data['date_from'])->startOfDay();
        $to = Carbon::parse($data['date_to'])->startOfDay();

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

        if (($values['status'] ?? null) === 'blocked') {
            $bookedNights = $property->availabilities()
                ->whereIn('date', $dates)
                ->where('status', 'booked')
                ->pluck('date')
                ->map(fn ($x) => substr((string) $x, 0, 10))
                ->all();
            $dates = array_values(array_diff($dates, $bookedNights));
            if (! $dates) {
                return back()->with('error', 'Those nights are booked and cannot be blocked.');
            }
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
    public function fullSync(Property $property, ChannexOutboxSender $sender)
    {
        $r = $sender->fullSync($property);

        $summary = "{$r['availability_dates']} availability date(s) and {$r['restriction_dates']} rate/restriction date(s) sent in {$r['calls']} call(s).";
        $notes = $r['messages'] ? ' '.implode(' ', $r['messages']) : '';

        ActivityLog::record(
            $r['failed'] ? 'property_channex_full_sync_failed' : 'property_channex_full_sync',
            "{$property->name}: full sync. {$summary}",
            'properties',
            $property
        );

        if ($r['failed']) {
            return back()->with('error', 'Full sync had a problem. '.$summary.$notes);
        }

        return back()->with('success', 'Full sync done. '.$summary.$notes);
    }
}
