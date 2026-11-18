<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $tables = ['room_photos', 'checklist_item_photos'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'retained')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->boolean('retained')->default(false)->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'retained')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('retained'));
            }
        }
    }
};
