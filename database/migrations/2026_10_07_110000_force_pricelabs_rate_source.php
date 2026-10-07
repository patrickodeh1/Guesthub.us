<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PriceLabs is the only rate source now; the old per-property choice is retired.
        DB::table('properties')->update(['rate_source' => 'guesthub']);
    }

    public function down(): void
    {
    }
};
