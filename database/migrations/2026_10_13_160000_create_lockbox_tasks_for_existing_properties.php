<?php

use App\Models\Property;
use App\Models\Task;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Property::query()->whereNotNull('lockbox_code')->where('lockbox_code', '!=', '')->each(function ($property) {
            $has = $property->propertyTasks()->where('tasks.type', 'lockbox')->exists();
            if ($has) {
                return;
            }
            $task = Task::create([
                'name' => 'Change lockbox code',
                'type' => 'lockbox',
                'phase' => 'during_cleaning',
                'is_sporadic' => false,
                'is_default' => false,
                'training_version' => 1,
            ]);
            $next = (int) DB::table('property_tasks')->where('property_id', $property->id)->max('sort_order') + 1;
            $property->propertyTasks()->attach($task->id, ['sort_order' => $next]);
        });
    }

    public function down(): void
    {
        // Removes every lockbox task (pivot rows cascade).
        Task::where('type', 'lockbox')->delete();
    }
};
