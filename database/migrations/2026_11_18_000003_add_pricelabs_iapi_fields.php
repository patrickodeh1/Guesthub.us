<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pricelabs_account_email')->nullable();
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->string('pricelabs_iapi_listing_id')->nullable()->unique();
            $table->string('pricelabs_mode', 8)->default('key');
            $table->timestamp('pricelabs_iapi_listing_pushed_at')->nullable();
            $table->timestamp('pricelabs_iapi_calendar_pushed_at')->nullable();
            $table->timestamp('pricelabs_iapi_last_price_received_at')->nullable();
            $table->text('pricelabs_iapi_last_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropUnique(['pricelabs_iapi_listing_id']);
            $table->dropColumn([
                'pricelabs_iapi_listing_id',
                'pricelabs_mode',
                'pricelabs_iapi_listing_pushed_at',
                'pricelabs_iapi_calendar_pushed_at',
                'pricelabs_iapi_last_price_received_at',
                'pricelabs_iapi_last_error',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pricelabs_account_email');
        });
    }
};
