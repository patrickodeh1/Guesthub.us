<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_photos', function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable()->after('room_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by');
        });
    }
};
