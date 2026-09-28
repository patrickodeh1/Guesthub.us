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
        $legacyLogo = DB::table('settings')->where('key', 'site_logo')->value('value');
        if ($legacyLogo !== null) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'application_logo_path'],
                ['value' => $legacyLogo]
            );
        }

        $legacyFavicon = DB::table('settings')->where('key', 'favicon')->value('value');
        if ($legacyFavicon !== null) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'favicon_path'],
                ['value' => $legacyFavicon]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['application_logo_path', 'favicon_path'])->delete();
    }
};
