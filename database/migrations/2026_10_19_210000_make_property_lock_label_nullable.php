<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE property_locks MODIFY label VARCHAR(255) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE property_locks SET label = 'Front Door' WHERE label IS NULL");
        DB::statement("ALTER TABLE property_locks MODIFY label VARCHAR(255) NOT NULL DEFAULT 'Front Door'");
    }
};
