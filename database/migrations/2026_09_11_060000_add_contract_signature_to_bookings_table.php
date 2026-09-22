<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Digital-signature capture for the guest rental agreement: the name the
 * guest typed (must match their government ID / booking name), plus the
 * IP address and browser user agent at the moment of signing. Stored now so
 * the agreement can be rendered as a PDF (with this signature evidence) later.
 * Nullable so existing bookings are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('contract_signed_name')->nullable()->after('contract_accepted_at');
            $table->string('contract_signed_ip', 45)->nullable()->after('contract_signed_name');
            $table->text('contract_signed_user_agent')->nullable()->after('contract_signed_ip');
            $table->string('contract_signed_device_id', 64)->nullable()->after('contract_signed_user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['contract_signed_name', 'contract_signed_ip', 'contract_signed_user_agent', 'contract_signed_device_id']);
        });
    }
};
