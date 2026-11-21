<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_channel_links', function (Blueprint $table) {
            if (! Schema::hasColumn('listing_channel_links', 'live')) {
                $table->boolean('live')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('listing_channel_links', function (Blueprint $table) {
            if (Schema::hasColumn('listing_channel_links', 'live')) {
                $table->dropColumn('live');
            }
        });
    }
};
