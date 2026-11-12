<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_receipts')) {
            Schema::create('payment_receipts', function (Blueprint $table) {
                $table->id();
                $table->morphs('payable');
                $table->string('role', 10); // guest = what the payer sent, host = what the host issued on receiving it
                $table->string('path');
                $table->string('original_name')->nullable();
                $table->string('mime', 100)->nullable();
                $table->unsignedInteger('size')->default(0);
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();
                $table->index(['payable_type', 'payable_id', 'role']);
            });
        }
        if (Schema::hasTable('bookings') && ! Schema::hasColumn('bookings', 'guest_payment_reported_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->timestamp('guest_payment_reported_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
        if (Schema::hasColumn('bookings', 'guest_payment_reported_at')) {
            Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('guest_payment_reported_at'));
        }
    }
};
