<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['room_photos', 'checklist_item_photos'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->timestamp('purged_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['room_photos', 'checklist_item_photos'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('purged_at');
            });
        }
    }
};
