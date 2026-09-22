<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Brand new table — cannot affect any existing property or booking
        // data by construction.
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            // 'deposit', 'parking', 'incidentals', 'early_checkin',
            // 'late_checkout'. Kept as a plain string (not a DB enum) so
            // adding a new charge type later is just a new value, no
            // migration needed.
            $table->string('type');

            $table->unsignedInteger('amount_cents');

            // 'pending', 'captured', 'failed'. ('held'/'partially_captured'/
            // 'released' reserved for a possible future hold-based deposit
            // flow — not used for now; every charge type, deposit included,
            // is captured immediately. See PaymentService.)
            $table->string('status')->default('pending');

            $table->string('stripe_payment_intent_id')->nullable();

            // Which "billing moment" produced this charge (see plan notes):
            // 'precheckin_approval', 'precheckin_submission',
            // 'early_checkin_granted', 'post_checkout'. Purely descriptive /
            // for admin visibility & debugging, not branched on by business
            // logic.
            $table->string('billing_moment')->nullable();

            $table->timestamp('captured_at')->nullable();
            $table->timestamp('released_at')->nullable();

            // Free-text context for admin visibility, e.g. "3.5 hrs late
            // checkout @ $15/hr" — avoids the admin having to reverse-engineer
            // an amount from raw numbers alone.
            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['booking_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
