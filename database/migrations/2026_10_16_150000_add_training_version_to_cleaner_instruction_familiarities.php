<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cleaner_instruction_familiarities', function (Blueprint $table) {
            $table->integer('training_version')->nullable();
        });

        // Existing counts stay valid: stamp them with the current version so only FUTURE edits reset them.
        DB::statement('UPDATE cleaner_instruction_familiarities SET training_version = (SELECT COALESCE(tasks.training_version, 1) FROM tasks WHERE tasks.id = cleaner_instruction_familiarities.task_id) WHERE instructional_video_id IS NULL');
        DB::statement('UPDATE cleaner_instruction_familiarities SET training_version = (SELECT COALESCE(instructional_videos.training_version, 1) FROM instructional_videos WHERE instructional_videos.id = cleaner_instruction_familiarities.instructional_video_id) WHERE instructional_video_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('cleaner_instruction_familiarities', function (Blueprint $table) {
            $table->dropColumn('training_version');
        });
    }
};
