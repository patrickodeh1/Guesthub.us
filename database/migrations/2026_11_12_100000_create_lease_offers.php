<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lease_offers')) {
            Schema::create('lease_offers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lease_application_id')->constrained('lease_applications')->cascadeOnDelete();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('booking_id')->nullable()->index();
                $table->string('status', 20)->default('draft');
                $table->string('token', 64)->nullable()->unique();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->unsignedBigInteger('monthly_rent_cents')->default(0);
                $table->unsignedBigInteger('deposit_cents')->default(0);
                $table->json('fees')->nullable();
                $table->unsignedBigInteger('total_cents')->default(0);
                $table->unsignedBigInteger('initial_due_cents')->default(0);
                $table->string('payment_method', 20)->nullable();
                $table->unsignedBigInteger('host_payment_method_id')->nullable();
                $table->timestamp('accept_by')->nullable();
                $table->longText('contract_html')->nullable();
                $table->string('contract_version', 20)->nullable();
                $table->text('host_signed_name')->nullable();
                $table->timestamp('host_signed_at')->nullable();
                $table->text('host_signed_ip')->nullable();
                $table->text('host_signed_user_agent')->nullable();
                $table->text('guest_signed_name')->nullable();
                $table->timestamp('guest_signed_at')->nullable();
                $table->text('guest_signed_ip')->nullable();
                $table->text('guest_signed_user_agent')->nullable();
                $table->string('guest_signed_device_id', 64)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('reminded_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->text('declined_reason')->nullable();
                $table->timestamp('expired_at')->nullable();
                $table->timestamp('withdrawn_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'accept_by']);
                $table->index(['property_id', 'status']);
            });
        }

        if (! Schema::hasTable('lease_installments')) {
            Schema::create('lease_installments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lease_offer_id')->constrained('lease_offers')->cascadeOnDelete();
                $table->string('kind', 20)->default('rent');
                $table->string('label');
                $table->date('due_date');
                $table->unsignedBigInteger('amount_cents')->default(0);
                $table->boolean('required_at_signing')->default(false);
                $table->string('status', 20)->default('pending');
                $table->string('method', 20)->nullable();
                $table->unsignedBigInteger('charge_id')->nullable()->index();
                $table->timestamp('paid_at')->nullable();
                $table->string('receipt_path')->nullable();
                $table->unsignedBigInteger('received_by')->nullable();
                $table->timestamp('reminded_at')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['lease_offer_id', 'due_date']);
                $table->index(['status', 'due_date']);
            });
        }

        Schema::table('lease_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('lease_applications', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            if (! Schema::hasColumn('lease_applications', 'decline_reason')) {
                $table->text('decline_reason')->nullable();
            }
            if (! Schema::hasColumn('lease_applications', 'sms_consent_at')) {
                $table->timestamp('sms_consent_at')->nullable();
            }
        });

        if (Schema::hasTable('sms_consent_events')) {
            Schema::table('sms_consent_events', function (Blueprint $table) {
                if (! Schema::hasColumn('sms_consent_events', 'lease_application_id')) {
                    $table->unsignedBigInteger('lease_application_id')->nullable()->index();
                }
            });
            // Lease applicants have no booking yet, so their consent rows need a null booking_id.
            Schema::table('sms_consent_events', function (Blueprint $table) {
                $table->unsignedBigInteger('booking_id')->nullable()->change();
            });
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'lease_application_id')) {
                $table->unsignedBigInteger('lease_application_id')->nullable()->index();
            }
            if (! Schema::hasColumn('bookings', 'lease_offer_id')) {
                $table->unsignedBigInteger('lease_offer_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        $drops = [
            'bookings' => ['lease_application_id', 'lease_offer_id'],
            'sms_consent_events' => ['lease_application_id'],
            'lease_applications' => ['approved_at', 'decline_reason', 'sms_consent_at'],
        ];
        foreach ($drops as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            foreach ($columns as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
        Schema::dropIfExists('lease_installments');
        Schema::dropIfExists('lease_offers');
    }
};
