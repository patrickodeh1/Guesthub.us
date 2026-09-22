<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Deliberately separate from deposit_verified_at, which stays
            // exactly as-is (manual admin action, untouched by this work).
            // This tracks the actual Stripe PaymentIntent for new,
            // Stripe-enabled bookings only.
            //
            // Values: null (not charged), 'captured', 'failed'. ('held' /
            // 'released' reserved for a possible future hold-based deposit
            // flow — not used for now, deposit is captured immediately like
            // every other charge type. See PaymentService.)
            $table->string('deposit_payment_status')->nullable()->after('contract_accepted_at');
            $table->string('deposit_stripe_payment_intent_id')->nullable()->after('deposit_payment_status');
            $table->unsignedInteger('deposit_amount_cents')->nullable()->after('deposit_stripe_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['deposit_payment_status', 'deposit_stripe_payment_intent_id', 'deposit_amount_cents']);
        });
    }
};
