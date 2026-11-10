<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                foreach ([
                    'accommodation_cents',
                    'cleaning_fee_cents',
                    'tax_cents',
                    'processing_fee_cents',
                    'total_cents',
                ] as $column) {
                    if (! Schema::hasColumn('bookings', $column)) {
                        $table->unsignedBigInteger($column)->nullable();
                    }
                }

                if (! Schema::hasColumn('bookings', 'payment_method')) {
                    $table->string('payment_method')->nullable();
                }
                if (! Schema::hasColumn('bookings', 'host_payment_method_id')) {
                    $table->unsignedBigInteger('host_payment_method_id')->nullable()->index();
                }
                if (! Schema::hasColumn('bookings', 'hold_expires_at')) {
                    $table->timestamp('hold_expires_at')->nullable()->index();
                }
                if (! Schema::hasColumn('bookings', 'payment_confirmed_at')) {
                    $table->timestamp('payment_confirmed_at')->nullable();
                }
                if (! Schema::hasColumn('bookings', 'guests_count')) {
                    $table->unsignedSmallInteger('guests_count')->nullable();
                }
                if (! Schema::hasColumn('bookings', 'direct_booking_reminded_at')) {
                    $table->timestamp('direct_booking_reminded_at')->nullable();
                }
                if (! Schema::hasColumn('bookings', 'direct_booking_request')) {
                    $table->boolean('direct_booking_request')->default(false);
                }
                if (! Schema::hasColumn('bookings', 'direct_booking_approved_at')) {
                    $table->timestamp('direct_booking_approved_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('charges')) {
            Schema::table('charges', function (Blueprint $table) {
                if (! Schema::hasColumn('charges', 'stripe_account_id')) {
                    $table->string('stripe_account_id')->nullable();
                }
                if (! Schema::hasColumn('charges', 'refunded_cents')) {
                    $table->unsignedBigInteger('refunded_cents')->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('charges')) {
            foreach (['refunded_cents', 'stripe_account_id'] as $column) {
                if (Schema::hasColumn('charges', $column)) {
                    Schema::table('charges', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table): void {
                if (Schema::hasColumn('bookings', 'hold_expires_at')) {
                    $table->dropIndex(['hold_expires_at']);
                }
                if (Schema::hasColumn('bookings', 'host_payment_method_id')) {
                    $table->dropIndex(['host_payment_method_id']);
                }
            });

            foreach ([
                'direct_booking_reminded_at',
                'direct_booking_approved_at',
                'direct_booking_request',
                'guests_count',
                'payment_confirmed_at',
                'hold_expires_at',
                'host_payment_method_id',
                'payment_method',
                'total_cents',
                'processing_fee_cents',
                'tax_cents',
                'cleaning_fee_cents',
                'accommodation_cents',
            ] as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }
};
