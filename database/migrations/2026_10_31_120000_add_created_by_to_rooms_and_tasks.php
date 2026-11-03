<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rooms', 'tasks'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'created_by')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('created_by')->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['rooms', 'tasks'] as $table) {
            if (Schema::hasColumn($table, 'created_by')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('created_by'));
            }
        }
    }
};
