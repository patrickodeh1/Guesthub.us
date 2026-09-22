<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE early_access_leads MODIFY role VARCHAR(255) NULL");
        } elseif (DB::getDriverName() === 'sqlite') {
            // SQLite has no ALTER MODIFY and doctrine/dbal isn't installed
            // for ->change(), so swap in a nullable column the portable way
            // (same technique as the two enum-CHECK migration fixes above).
            Schema::table('early_access_leads', function (Blueprint $table) {
                $table->string('role_tmp')->nullable()->after('role');
            });
            DB::statement('UPDATE early_access_leads SET role_tmp = role');
            Schema::table('early_access_leads', function (Blueprint $table) {
                $table->dropColumn('role');
            });
            Schema::table('early_access_leads', function (Blueprint $table) {
                $table->renameColumn('role_tmp', 'role');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE early_access_leads MODIFY role VARCHAR(255) NOT NULL");
        }
    }
};
