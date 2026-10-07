<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $t) {
            $t->string('pricelabs_listing_id')->nullable();
            $t->string('pricelabs_pms', 50)->nullable();
            $t->string('pricelabs_last_refreshed_at', 40)->nullable();
            $t->timestamp('pricelabs_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('properties', fn (Blueprint $t) => $t->dropColumn(['pricelabs_listing_id', 'pricelabs_pms', 'pricelabs_last_refreshed_at', 'pricelabs_synced_at']));
    }
};
