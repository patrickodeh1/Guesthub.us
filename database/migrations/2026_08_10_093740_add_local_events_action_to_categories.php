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
            DB::statement("ALTER TABLE categories MODIFY action ENUM('content', 'door_lock', 'local_events') NOT NULL DEFAULT 'content'");
        } elseif (DB::getDriverName() === 'sqlite') {
            // SQLite emulates enum() as a CHECK constraint baked in at table
            // creation (no ALTER for CHECK, and doctrine/dbal isn't
            // installed for a ->change() call). Widen it by swapping in a
            // plain string column with the same default — the real
            // constraint on allowed values is already enforced at the
            // application layer (validation rules), so this only affects
            // the SQLite test database, never production (which is MySQL).
            Schema::table('categories', function (Blueprint $table) {
                $table->string('action_tmp')->default('content')->after('action');
            });
            DB::statement('UPDATE categories SET action_tmp = action');
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('action');
            });
            Schema::table('categories', function (Blueprint $table) {
                $table->renameColumn('action_tmp', 'action');
            });
        }

        DB::table('categories')->updateOrInsert(
            ['slug' => 'local-events'],
            [
                'title' => 'Local Events',
                'icon' => 'Local Events',
                'action' => 'local_events',
                'sort_order' => 999,
                'active' => true,
                'is_global' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('categories')->where('slug', 'local-events')->delete();

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE categories MODIFY action ENUM('content', 'door_lock') NOT NULL DEFAULT 'content'");
        }
    }
};
