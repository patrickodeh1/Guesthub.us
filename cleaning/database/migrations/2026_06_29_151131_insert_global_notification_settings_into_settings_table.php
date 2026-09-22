<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        DB::table('settings')->insertOrIgnore([
            ['key' => 'notify_cleaning_started_global', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'notify_cleaning_finished_global', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'notify_photo_started_global', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'notify_task_notes_global', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'notify_cleaning_started_global',
            'notify_cleaning_finished_global',
            'notify_photo_started_global',
            'notify_task_notes_global'
        ])->delete();
    }
};
