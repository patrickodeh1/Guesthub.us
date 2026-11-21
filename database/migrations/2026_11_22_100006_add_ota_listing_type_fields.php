<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('property_listings', 'property_type')) {
                $table->string('property_type', 40)->default('apartment');
            }
            if (! Schema::hasColumn('property_listings', 'room_type')) {
                $table->string('room_type', 40)->default('entire_home');
            }
        });
    }

    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table) {
            if (Schema::hasColumn('property_listings', 'room_type')) {
                $table->dropColumn('room_type');
            }
            if (Schema::hasColumn('property_listings', 'property_type')) {
                $table->dropColumn('property_type');
            }
        });
    }
};
