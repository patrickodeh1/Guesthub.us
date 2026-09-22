<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('charges')->where('status', 'captured')->update(['status' => 'success']);
        DB::table('bookings')->where('deposit_payment_status', 'captured')->update(['deposit_payment_status' => 'success']);
    }

    public function down(): void
    {
        DB::table('charges')->where('status', 'success')->update(['status' => 'captured']);
        DB::table('bookings')->where('deposit_payment_status', 'success')->update(['deposit_payment_status' => 'captured']);
    }
};
