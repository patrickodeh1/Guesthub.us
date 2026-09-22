<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    DB::table('instruction_steps')
        ->where('type', 'parking')
        ->update([
            'type' => 'checkin',
            'visibility' => 'parkers_only',
        ]);
}

public function down(): void
{
    DB::table('instruction_steps')
        ->where('type', 'checkin')
        ->where('visibility', 'parkers_only')
        ->update(['type' => 'parking']);
}
};
