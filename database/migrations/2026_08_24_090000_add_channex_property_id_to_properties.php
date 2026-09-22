<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Nullable, admin fills in per-property during setup — mirrors
            // task 0's pattern. Existing properties are unaffected until an
            // admin explicitly maps them; nothing reads this column unless
            // it's set.
            $table->string('channex_property_id')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('channex_property_id');
        });
    }
};
