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
        Schema::table('training_completions', function (Blueprint $table) {
            $table->unsignedInteger('views_completed')->default(0)->after('progress');
        });

        // Backfill logic for existing completed records
        // If a record is already marked as 'completed', we don't want to invalidate it.
        // We will look up the required_views from either the task or the video and set views_completed to that value.
        // This ensures the cleaner isn't forced to re-watch a video they've already "completed" under the old rules.
        
        $completions = \Illuminate\Support\Facades\DB::table('training_completions')
            ->where('status', 'completed')
            ->get();

        foreach ($completions as $completion) {
            $requiredViews = 1;

            if ($completion->instructional_video_id) {
                $video = \Illuminate\Support\Facades\DB::table('instructional_videos')
                    ->where('id', $completion->instructional_video_id)
                    ->first();
                if ($video && isset($video->required_views)) {
                    $requiredViews = max(1, (int)$video->required_views);
                }
            } elseif ($completion->task_id) {
                $task = \Illuminate\Support\Facades\DB::table('tasks')
                    ->where('id', $completion->task_id)
                    ->first();
                if ($task && isset($task->required_views)) {
                    $requiredViews = max(1, (int)$task->required_views);
                }
            }

            \Illuminate\Support\Facades\DB::table('training_completions')
                ->where('id', $completion->id)
                ->update(['views_completed' => $requiredViews]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_completions', function (Blueprint $table) {
            $table->dropColumn('views_completed');
        });
    }
};
