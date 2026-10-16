<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'id_zip')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('id_zip', 10)->nullable()->after('id_number');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('id_zip');
        });
    }
};
