<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE property_tasks MODIFY show_after_step_id BIGINT NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE property_tasks SET show_after_step_id = NULL WHERE show_after_step_id < 0');
        DB::statement('ALTER TABLE property_tasks MODIFY show_after_step_id BIGINT UNSIGNED NULL');
    }
};
