<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings')
            || ! Schema::hasColumn('bookings', 'lease_offer_id')
            || ! Schema::hasColumn('bookings', 'lease_application_id')
            || ! Schema::hasColumn('bookings', 'booking_platform')) {
            return;
        }

        DB::table('bookings')
            ->where('source', 'direct')
            ->where(function ($query): void {
                $query->whereNotNull('lease_offer_id')
                    ->orWhereNotNull('lease_application_id');
            })
            ->update([
                'source' => 'lease',
                'booking_platform' => 'Lease',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally irreversible: the original source values are ambiguous.
    }
};
