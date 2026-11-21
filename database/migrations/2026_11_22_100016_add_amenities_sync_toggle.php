<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('property_listings', 'amenities_sync_enabled')) {
                $table->boolean('amenities_sync_enabled')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table) {
            if (Schema::hasColumn('property_listings', 'amenities_sync_enabled')) {
                $table->dropColumn('amenities_sync_enabled');
            }
        });
    }
};
