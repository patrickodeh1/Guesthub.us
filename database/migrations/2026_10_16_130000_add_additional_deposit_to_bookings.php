<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('additional_deposit', 10, 2)->nullable();
            // Default true so bookings created from now on use the new ledger.
            $table->boolean('additional_deposit_mode')->default(true);
        });

        // Every booking that already exists keeps the original behavior untouched.
        DB::table('bookings')->update(['additional_deposit_mode' => false]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['additional_deposit', 'additional_deposit_mode']);
        });
    }
};
