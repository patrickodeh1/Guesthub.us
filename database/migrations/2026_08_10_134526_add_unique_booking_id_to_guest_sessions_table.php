<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Dedupe: keep only the most recent session per booking_id, in case
        // any rows were created before the unique constraint existed.
        $duplicateIds = DB::table('guest_sessions')
            ->select('id')
            ->whereNotIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('guest_sessions')
                    ->groupBy('booking_id');
            })
            ->pluck('id');

        if ($duplicateIds->isNotEmpty()) {
            DB::table('guest_sessions')->whereIn('id', $duplicateIds)->delete();
        }

        Schema::table('guest_sessions', function (Blueprint $table) {
            $table->unique('booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('guest_sessions', function (Blueprint $table) {
            $table->dropUnique(['booking_id']);
        });
    }
};
