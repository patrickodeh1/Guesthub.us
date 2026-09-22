<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_locks', function (Blueprint $table) {
            $table->unique('seam_device_id');
        });
    }

    public function down(): void
    {
        Schema::table('property_locks', function (Blueprint $table) {
            $table->dropUnique(['seam_device_id']);
        });
    }
};
