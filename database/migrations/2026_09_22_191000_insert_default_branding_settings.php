<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('settings')->insertOrIgnore([
            ['key' => 'application_logo_path', 'value' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'favicon_path', 'value' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'site_name', 'value' => config('app.name', 'HK Checklist'), 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'theme_color', 'value' => '#842eb8', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'button_primary_color', 'value' => '#842eb8', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'button_success_color', 'value' => '#10b981', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'button_danger_color', 'value' => '#ef4444', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'button_warning_color', 'value' => '#f59e0b', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'button_info_color', 'value' => '#06b6d4', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'application_logo_path',
            'favicon_path',
            'site_name',
            'theme_color',
            'button_primary_color',
            'button_success_color',
            'button_danger_color',
            'button_warning_color',
            'button_info_color',
        ])->delete();
    }
};
