<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('property_locks', function (Blueprint $table) {
                $table->string('label', 255)->nullable()->default(null)->change();
            });
        } else {
            DB::statement('ALTER TABLE property_locks MODIFY label VARCHAR(255) NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        DB::statement("UPDATE property_locks SET label = 'Front Door' WHERE label IS NULL");
        DB::statement("ALTER TABLE property_locks MODIFY label VARCHAR(255) NOT NULL DEFAULT 'Front Door'");
    }
};
