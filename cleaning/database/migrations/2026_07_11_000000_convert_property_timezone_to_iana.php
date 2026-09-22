<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert Property.timezone from fixed UTC offsets (e.g. "-05:00")
     * to proper IANA timezone names (e.g. "America/New_York").
     *
     * Fixed offsets cannot handle DST transitions, which causes every
     * timestamp to be wrong half the year for any property that observes DST.
     */
    public function up(): void
    {
        // Map of UTC-offset values currently stored → IANA timezone names.
        // The most common US zones are given specific mappings; the rest
        // use a best-effort mapping.  All existing data was examined and
        // confirmed to be US-centric with the client.
        $map = [
            // Americas
            '-12:00' => 'Etc/GMT+12',
            '-11:00' => 'Pacific/Midway',
            '-10:00' => 'Pacific/Honolulu',
            '-09:50' => 'Pacific/Marquesas',
            '-09:00' => 'America/Anchorage',
            '-08:00' => 'America/Los_Angeles',
            '-07:00' => 'America/Denver',
            '-06:00' => 'America/Chicago',
            '-05:00' => 'America/New_York',
            '-04:50' => 'America/Caracas',
            '-04:00' => 'America/Halifax',
            '-03:50' => 'America/St_Johns',
            '-03:00' => 'America/Sao_Paulo',
            '-02:00' => 'Atlantic/South_Georgia',
            '-01:00' => 'Atlantic/Azores',

            // Europe / Africa
            '+00:00' => 'Europe/London',
            '+01:00' => 'Europe/Paris',
            '+02:00' => 'Europe/Helsinki',
            '+03:00' => 'Europe/Moscow',
            '+03:50' => 'Asia/Tehran',
            '+04:00' => 'Asia/Dubai',
            '+04:50' => 'Asia/Kabul',

            // Asia / Oceania
            '+05:00' => 'Asia/Karachi',
            '+05:50' => 'Asia/Kolkata',
            '+05:75' => 'Asia/Kathmandu',
            '+06:00' => 'Asia/Dhaka',
            '+06:50' => 'Asia/Yangon',
            '+07:00' => 'Asia/Bangkok',
            '+08:00' => 'Asia/Singapore',
            '+08:75' => 'Australia/Eucla',
            '+09:00' => 'Asia/Tokyo',
            '+09:50' => 'Australia/Adelaide',
            '+10:00' => 'Australia/Sydney',
            '+10:50' => 'Australia/Lord_Howe',
            '+11:00' => 'Pacific/Guadalcanal',
            '+11:50' => 'Pacific/Norfolk',
            '+12:00' => 'Pacific/Auckland',
            '+12:75' => 'Pacific/Chatham',
            '+13:00' => 'Pacific/Apia',
            '+14:00' => 'Pacific/Kiritimati',
        ];

        foreach ($map as $offset => $iana) {
            DB::table('properties')
                ->where('timezone', $offset)
                ->update(['timezone' => $iana]);
        }

        // Fallback: convert any remaining offsets that weren't in the map.
        // If the value looks like an offset (+/-HH:MM) but wasn't mapped,
        // default to America/New_York (the app's existing default).
        DB::table('properties')
            ->whereRaw("timezone LIKE '+%' OR timezone LIKE '-%'")
            ->update(['timezone' => 'America/New_York']);

        // Ensure any empty or null values get a sensible default
        DB::table('properties')
            ->where(function ($q) {
                $q->whereNull('timezone')
                  ->orWhere('timezone', '');
            })
            ->update(['timezone' => 'America/New_York']);
    }

    /**
     * Reverse the migrations — convert back to offsets.
     * This is lossy (we lose DST awareness) but is provided for rollback safety.
     */
    public function down(): void
    {
        $reverseMap = [
            'Pacific/Honolulu'     => '-10:00',
            'America/Anchorage'    => '-09:00',
            'America/Los_Angeles'  => '-08:00',
            'America/Denver'       => '-07:00',
            'America/Chicago'      => '-06:00',
            'America/New_York'     => '-05:00',
            'America/Halifax'      => '-04:00',
            'America/St_Johns'     => '-03:50',
            'America/Sao_Paulo'    => '-03:00',
            'Europe/London'        => '+00:00',
            'Europe/Paris'         => '+01:00',
            'Asia/Dubai'           => '+04:00',
            'Asia/Kolkata'         => '+05:50',
            'Asia/Tokyo'           => '+09:00',
            'Australia/Sydney'     => '+10:00',
            'Pacific/Auckland'     => '+12:00',
        ];

        foreach ($reverseMap as $iana => $offset) {
            DB::table('properties')
                ->where('timezone', $iana)
                ->update(['timezone' => $offset]);
        }
    }
};
