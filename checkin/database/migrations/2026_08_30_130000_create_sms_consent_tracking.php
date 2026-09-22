<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_consent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone');
            $table->string('event_type');
            $table->boolean('checkbox_status')->nullable();
            $table->text('disclosure_text')->nullable();
            $table->string('disclosure_version')->nullable();
            $table->string('terms_version')->nullable();
            $table->string('privacy_version')->nullable();
            $table->string('page_url')->nullable();
            $table->string('guest_name')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->string('host_name')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('opt_in_method')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['phone', 'event_type']);
            $table->index(['booking_id', 'event_type']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('sms_consent_at')->nullable()->after('contract_accepted_at');
            $table->string('sms_consent_version')->nullable()->after('sms_consent_at');
            $table->boolean('sms_consent_opted_in')->default(false)->after('sms_consent_version');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['sms_consent_at', 'sms_consent_version', 'sms_consent_opted_in']);
        });

        Schema::dropIfExists('sms_consent_events');
    }
};
