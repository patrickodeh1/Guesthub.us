<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->boolean('notify_cleaning_started')->default(false);
            $table->boolean('notify_cleaning_finished')->default(false);
            $table->boolean('notify_photo_started')->default(false);
            $table->boolean('notify_task_notes')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'notify_cleaning_started',
                'notify_cleaning_finished',
                'notify_photo_started',
                'notify_task_notes'
            ]);
        });
    }
};
