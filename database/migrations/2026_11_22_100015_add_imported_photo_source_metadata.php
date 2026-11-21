<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_photos', function (Blueprint $table) {
            if (! Schema::hasColumn('listing_photos', 'source_channel')) {
                $table->string('source_channel', 32)->nullable();
            }
            if (! Schema::hasColumn('listing_photos', 'source_listing_id')) {
                $table->string('source_listing_id', 64)->nullable();
            }
            if (! Schema::hasColumn('listing_photos', 'source_photo_id')) {
                $table->string('source_photo_id', 128)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('listing_photos', function (Blueprint $table) {
            foreach (['source_channel', 'source_listing_id', 'source_photo_id'] as $column) {
                if (Schema::hasColumn('listing_photos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
