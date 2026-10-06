<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('id_first_name')->nullable()->after('id_name');
            $table->string('id_last_name')->nullable()->after('id_first_name');
            $table->string('id_scan_source', 20)->nullable()->after('id_scan_status');
            $table->string('id_name_match', 20)->nullable()->after('id_scan_source');
            $table->unsignedTinyInteger('id_scan_attempts')->default(0)->after('id_name_match');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['id_first_name', 'id_last_name', 'id_scan_source', 'id_name_match', 'id_scan_attempts']);
        });
    }
};
