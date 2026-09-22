<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('photo_id_front_approved_at')->nullable()->after('photo_id_path');
            $table->text('photo_id_front_declined_reason')->nullable()->after('photo_id_front_approved_at');
            $table->timestamp('photo_id_back_approved_at')->nullable()->after('photo_id_back_path');
            $table->text('photo_id_back_declined_reason')->nullable()->after('photo_id_back_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'photo_id_front_approved_at',
                'photo_id_front_declined_reason',
                'photo_id_back_approved_at',
                'photo_id_back_declined_reason',
            ]);
        });
    }
};
