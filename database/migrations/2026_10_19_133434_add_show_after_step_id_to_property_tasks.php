<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('property_tasks', 'show_after_step_id')) {
            Schema::table('property_tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('show_after_step_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('property_tasks', 'show_after_step_id')) {
            Schema::table('property_tasks', function (Blueprint $table) {
                $table->dropColumn('show_after_step_id');
            });
        }
    }
};
