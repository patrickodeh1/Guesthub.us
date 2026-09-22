<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill any users where is_active is NULL to true (active).
     * Users created before the is_active column was added may have NULL values
     * which were incorrectly treated as deactivated.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('is_active')
            ->update(['is_active' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reliably reverse — we don't know which were originally NULL
    }
};
