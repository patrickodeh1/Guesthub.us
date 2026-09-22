<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Forward-only: only ever set at the moment of acceptance, never
            // re-checked against a "current" version later. Existing
            // bookings simply stay null — they were never asked to accept
            // anything, and nothing in the app requires this to be filled.
            $table->string('contract_version')->nullable()->after('deposit_verified_at');
            $table->timestamp('contract_accepted_at')->nullable()->after('contract_version');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['contract_version', 'contract_accepted_at']);
        });
    }
};
