<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data read off the guest's government ID when it's uploaded (via the ID
 * scanner): date of birth (for age/compliance), expiry date (so an expired
 * document can be caught), the document number, and the scan status. All
 * nullable so existing bookings are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->date('id_date_of_birth')->nullable()->after('id_type');
            $table->unsignedTinyInteger('id_age')->nullable()->after('id_date_of_birth');
            $table->date('id_expiry_date')->nullable()->after('id_age');
            $table->string('id_number')->nullable()->after('id_expiry_date');
            $table->string('id_scan_status')->nullable()->after('id_number');
            $table->timestamp('id_scanned_at')->nullable()->after('id_scan_status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'id_date_of_birth', 'id_age', 'id_expiry_date',
                'id_number', 'id_scan_status', 'id_scanned_at',
            ]);
        });
    }
};
