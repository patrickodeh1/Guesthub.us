<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('sms_consent_opted_in');
            $table->string('terms_accepted_version')->nullable()->after('terms_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'terms_accepted_version']);
        });
    }
};
