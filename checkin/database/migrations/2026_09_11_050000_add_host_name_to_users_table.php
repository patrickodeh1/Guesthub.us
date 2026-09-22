<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The host / business name responsible for the listings, captured when a user
 * account is created. Used as the "host" party on guest-facing agreements
 * (auto-inserted into the rental agreement). Nullable so existing users are
 * unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('host_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('host_name');
        });
    }
};
