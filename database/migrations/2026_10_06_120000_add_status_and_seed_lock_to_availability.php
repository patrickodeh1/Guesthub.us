<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_availabilities', function (Blueprint $table) {
            $table->string('status', 10)->default('available')->after('is_available'); // available|booked|blocked
        });
        Schema::table('properties', function (Blueprint $table) {
            $table->timestamp('channex_availability_seeded_at')->nullable()->after('channex_room_type_id');
        });
        DB::table('property_availabilities')->where('is_available', false)->update(['status' => 'blocked']);
    }

    public function down(): void
    {
        Schema::table('property_availabilities', fn (Blueprint $t) => $t->dropColumn('status'));
        Schema::table('properties', fn (Blueprint $t) => $t->dropColumn('channex_availability_seeded_at'));
    }
};
