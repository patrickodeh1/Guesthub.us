<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('property_listings', 'repull_listing_id')) {
            Schema::table('property_listings', function (Blueprint $table) {
                $table->string('repull_listing_id', 64)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('property_listings', 'repull_listing_id')) {
            Schema::table('property_listings', function (Blueprint $table) {
                $table->dropUnique(['repull_listing_id']);
                $table->dropColumn('repull_listing_id');
            });
        }
    }
};
