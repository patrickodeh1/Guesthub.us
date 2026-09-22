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
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('is_required_during_task')->default(false)->after('is_required_before_start');
            $table->integer('required_views')->default(1)->after('is_required_during_task');
        });

        Schema::table('instructional_videos', function (Blueprint $table) {
            $table->boolean('is_required_during_task')->default(false)->after('is_required_before_start');
            $table->integer('required_views')->default(1)->after('is_required_during_task');
        });

        Schema::table('cleaner_instruction_familiarities', function (Blueprint $table) {
            $table->foreignUlid('instructional_video_id')->nullable()->after('task_id')->constrained('instructional_videos')->nullOnDelete();
            // We need to drop the unique constraint if one exists on (user_id, task_id) because task_id can be null or we track video_id now.
            // Wait, cleaner_instruction_familiarities doesn't necessarily have a unique constraint enforced in DB, let's check. 
            // It's usually firstOrCreate. We will just make task_id nullable if it's not already.
            $table->foreignId('task_id')->nullable()->change();
        });

        Schema::create('instructional_video_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignUlid('instructional_video_id')->constrained('instructional_videos')->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['task_id', 'instructional_video_id'], 'task_video_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructional_video_task');

        Schema::table('cleaner_instruction_familiarities', function (Blueprint $table) {
            $table->dropForeign(['instructional_video_id']);
            $table->dropColumn('instructional_video_id');
            // Revert task_id nullable change is tricky in sqlite without full rebuild, so we'll leave it or just drop column if needed.
            // SQLite doesn't support dropping foreign keys easily, but this is a standard down method.
        });

        Schema::table('instructional_videos', function (Blueprint $table) {
            $table->dropColumn(['is_required_during_task', 'required_views']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['is_required_during_task', 'required_views']);
        });
    }
};
