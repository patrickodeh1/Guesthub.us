<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Bookings that have not completed pre-check-in and have no typed
        // incidentals hold are treated as new. Everything else keeps the
        // original ledger behavior. Amounts are not changed.
        DB::table('bookings')
            ->where('additional_deposit_mode', false)
            ->where(fn ($w) => $w->whereNull('status')->orWhere('status', 'pending'))
            ->whereNull('incidentals_charge')
            ->whereNull('cancelled_at')
            ->whereNull('checked_out_at')
            ->update(['additional_deposit_mode' => true]);
    }

    public function down(): void
    {
        // Intentionally a no-op: the first migration's down() drops the column.
    }
};
