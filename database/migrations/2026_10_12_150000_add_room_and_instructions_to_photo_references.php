<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('property_photo_references', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable()->index();
            $table->text('instructions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('property_photo_references', function (Blueprint $table) {
            $table->dropColumn(['room_id', 'instructions']);
        });
    }
};
