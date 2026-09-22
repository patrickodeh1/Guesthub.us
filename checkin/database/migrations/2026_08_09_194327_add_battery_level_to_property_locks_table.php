<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_locks', function (Blueprint $table) {
            $table->unsignedTinyInteger('battery_level')->nullable()->after('last_status_at');
        });
    }

    public function down(): void
    {
        Schema::table('property_locks', function (Blueprint $table) {
            $table->dropColumn('battery_level');
        });
    }
};
