<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('rooms', 'photo_guide_text')) {
                $table->text('photo_guide_text')->nullable();
            }
            if (! Schema::hasColumn('rooms', 'photo_guide_seconds')) {
                $table->unsignedSmallInteger('photo_guide_seconds')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['photo_guide_text', 'photo_guide_seconds']);
        });
    }
};
