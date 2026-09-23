<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('is_pre_arrival')->default(false);
            $table->boolean('is_required_before_start')->default(false);
            $table->integer('pre_arrival_display_order')->default(0);
            $table->integer('estimated_duration_minutes')->nullable();
            $table->string('training_frequency')->default('once_ever');
            $table->integer('completion_threshold_percent')->default(100);
            $table->string('instruction_completion_method')->default('manual');
            $table->integer('training_version')->nullable()->default(1);
        });

        Schema::table('instructional_videos', function (Blueprint $table) {
            $table->boolean('is_pre_arrival')->default(false);
            $table->boolean('is_required_before_start')->default(false);
            $table->integer('pre_arrival_display_order')->default(0);
            // InstructionalVideo already has duration_seconds
            $table->string('training_frequency')->default('once_ever');
            $table->integer('completion_threshold_percent')->default(90);
            $table->string('instruction_completion_method')->default('time');
            $table->integer('training_version')->nullable()->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'is_pre_arrival',
                'is_required_before_start',
                'pre_arrival_display_order',
                'estimated_duration_minutes',
                'training_frequency',
                'completion_threshold_percent',
                'instruction_completion_method',
                'training_version'
            ]);
        });

        Schema::table('instructional_videos', function (Blueprint $table) {
            $table->dropColumn([
                'is_pre_arrival',
                'is_required_before_start',
                'pre_arrival_display_order',
                'training_frequency',
                'completion_threshold_percent',
                'instruction_completion_method',
                'training_version'
            ]);
        });
    }
};
