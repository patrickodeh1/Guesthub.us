<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Billing tier for an early check-in exception. Deliberately separate
            // from the existing `early_checkin` boolean (which controls address
            // visibility, task-unrelated) so this never changes that behavior.
            $table->string('early_checkin_tier')->nullable()->after('early_checkin');

            // Late checkout billing. All fields here are admin-entered and only
            // ever read by the charge calculation — none of them are written to
            // or read by the auto-checkout scheduled command (task 23) or the
            // door-lock expiry logic, by design, to keep the two features from
            // affecting each other.
            $table->string('late_checkout_type')->nullable()->after('checked_out_at'); // 'authorized' | 'unauthorized'
            $table->decimal('late_checkout_hours', 8, 2)->nullable()->after('late_checkout_type'); // manual entry, authorized only
            $table->timestamp('late_checkout_actual_time')->nullable()->after('late_checkout_hours'); // manual entry, unauthorized only
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'early_checkin_tier', 'late_checkout_type', 'late_checkout_hours', 'late_checkout_actual_time',
            ]);
        });
    }
};
