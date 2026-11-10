<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lease_applications')) {
            Schema::create('lease_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('listing_id')->constrained('property_listings')->cascadeOnDelete();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->string('status', 30)->default('draft');
                $table->string('fee_status', 20)->default('unpaid');
                $table->string('payment_method', 20)->nullable();
                $table->foreignId('host_payment_method_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedBigInteger('application_fee_cents')->default(0);
                $table->unsignedBigInteger('processing_fee_cents')->default(0);
                $table->unsignedBigInteger('total_fee_cents')->default(0);

                $table->text('token')->nullable();
                $table->text('applicant_name')->nullable();
                $table->text('applicant_email')->nullable();
                $table->text('applicant_phone')->nullable();

                $table->text('id_name')->nullable();
                $table->text('id_address')->nullable();
                $table->text('id_number_last4')->nullable();
                $table->string('id_scan_status', 30)->default('pending');
                $table->unsignedTinyInteger('id_scan_attempts')->default(0);
                $table->string('id_scan_path')->nullable();

                $table->date('move_in_date')->nullable();
                $table->unsignedSmallInteger('lease_term_months')->nullable();
                $table->unsignedSmallInteger('occupants')->nullable();
                $table->text('pets')->nullable();
                $table->text('vehicles')->nullable();
                $table->text('current_address')->nullable();
                $table->unsignedSmallInteger('current_address_months')->nullable();
                $table->text('employer')->nullable();
                $table->text('job_title')->nullable();
                $table->text('supervisor_name')->nullable();
                $table->text('supervisor_phone')->nullable();
                $table->text('monthly_income_cents')->nullable();
                $table->text('years_employed')->nullable();
                $table->text('previous_landlord_name')->nullable();
                $table->text('previous_landlord_phone')->nullable();
                $table->text('emergency_contact_name')->nullable();
                $table->text('emergency_contact_phone')->nullable();

                $table->timestamp('consent_at')->nullable();
                $table->text('consent_signature')->nullable();
                $table->text('consent_ip')->nullable();
                $table->text('consent_user_agent')->nullable();
                $table->text('host_notes')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->timestamp('screening_requested_at')->nullable();
                $table->timestamps();

                $table->index(['property_id', 'status']);
                $table->index(['status', 'declined_at']);
                $table->index(['status', 'created_at']);
            });
        }

        if (Schema::hasTable('charges')) {
            Schema::table('charges', function (Blueprint $table) {
                if (Schema::hasColumn('charges', 'booking_id')) {
                    $table->unsignedBigInteger('booking_id')->nullable()->change();
                }
                if (! Schema::hasColumn('charges', 'lease_application_id')) {
                    $table->foreignId('lease_application_id')
                        ->nullable()
                        ->constrained('lease_applications')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('charges') && Schema::hasColumn('charges', 'lease_application_id')) {
            Schema::table('charges', function (Blueprint $table) {
                $table->dropForeign(['lease_application_id']);
            });
            DB::table('charges')->whereNotNull('lease_application_id')->delete();
            Schema::table('charges', fn (Blueprint $table) => $table->dropColumn('lease_application_id'));
        }

        if (Schema::hasTable('charges') && Schema::hasColumn('charges', 'booking_id')) {
            Schema::table('charges', function (Blueprint $table) {
                $table->unsignedBigInteger('booking_id')->nullable(false)->change();
            });
        }

        Schema::dropIfExists('lease_applications');
    }
};
