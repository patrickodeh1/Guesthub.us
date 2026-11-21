<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_photos', function (Blueprint $table) {
            if (! Schema::hasColumn('listing_photos', 'repull_photo_listing_id')) {
                $table->string('repull_photo_listing_id', 64)->nullable();
            }
            if (! Schema::hasColumn('listing_photos', 'repull_photo_path')) {
                $table->string('repull_photo_path', 512)->nullable();
            }
            if (! Schema::hasColumn('listing_photos', 'repull_photo_url')) {
                $table->text('repull_photo_url')->nullable();
            }
            if (! Schema::hasColumn('listing_photos', 'repull_uploaded_at')) {
                $table->timestamp('repull_uploaded_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('listing_photos', function (Blueprint $table) {
            foreach (['repull_photo_listing_id', 'repull_photo_path', 'repull_photo_url', 'repull_uploaded_at'] as $column) {
                if (Schema::hasColumn('listing_photos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
