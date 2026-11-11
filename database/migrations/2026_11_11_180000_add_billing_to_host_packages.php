<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('host_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('host_packages', 'billing_source')) {
                $table->string('billing_source', 20)->default('admin');
            }
            if (! Schema::hasColumn('host_packages', 'stripe_customer_id')) {
                $table->string('stripe_customer_id')->nullable()->index();
            }
            if (! Schema::hasColumn('host_packages', 'stripe_subscription_id')) {
                $table->string('stripe_subscription_id')->nullable()->unique();
            }
            if (! Schema::hasColumn('host_packages', 'current_period_end')) {
                $table->timestamp('current_period_end')->nullable();
            }
            if (! Schema::hasColumn('host_packages', 'cancel_at_period_end')) {
                $table->boolean('cancel_at_period_end')->default(false);
            }
            if (! Schema::hasColumn('host_packages', 'past_due_since')) {
                $table->timestamp('past_due_since')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('host_packages', function (Blueprint $table) {
            foreach (['billing_source', 'stripe_customer_id', 'stripe_subscription_id', 'current_period_end', 'cancel_at_period_end', 'past_due_since'] as $c) {
                if (Schema::hasColumn('host_packages', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
