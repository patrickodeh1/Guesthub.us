<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_channel_links', function (Blueprint $table) {
            if (! Schema::hasColumn('listing_channel_links', 'sync_category')) {
                $table->string('sync_category', 50)->nullable();
            }
            if (! Schema::hasColumn('listing_channel_links', 'writable')) {
                $table->boolean('writable')->nullable();
            }
            if (! Schema::hasColumn('listing_channel_links', 'status_checked_at')) {
                $table->timestamp('status_checked_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('listing_channel_links', function (Blueprint $table) {
            foreach (['sync_category', 'writable', 'status_checked_at'] as $column) {
                if (Schema::hasColumn('listing_channel_links', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
