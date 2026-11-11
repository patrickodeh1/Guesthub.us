<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('packages')) {
            Schema::create('packages', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('price_cents')->default(0);
                $table->foreignId('parent_id')->nullable()->constrained('packages')->nullOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->unsignedSmallInteger('trial_days')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('package_features')) {
            Schema::create('package_features', function (Blueprint $table) {
                $table->id();
                $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
                $table->string('feature_key');
                $table->foreign('feature_key')->references('key')->on('features')->cascadeOnDelete();
                $table->unique(['package_id', 'feature_key']);
            });
        }

        if (! Schema::hasTable('host_packages')) {
            Schema::create('host_packages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('package_id')->constrained('packages')->restrictOnDelete();
                $table->string('status', 20)->default('active');
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('features', 'billing_unit')) {
            Schema::table('features', fn (Blueprint $t) => $t->string('billing_unit', 20)->default('flat'));
        }
        if (! Schema::hasColumn('host_features', 'quantity')) {
            Schema::table('host_features', fn (Blueprint $t) => $t->unsignedInteger('quantity')->default(1));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('host_features', 'quantity')) {
            Schema::table('host_features', fn (Blueprint $t) => $t->dropColumn('quantity'));
        }
        if (Schema::hasColumn('features', 'billing_unit')) {
            Schema::table('features', fn (Blueprint $t) => $t->dropColumn('billing_unit'));
        }
        Schema::dropIfExists('host_packages');
        Schema::dropIfExists('package_features');
        Schema::dropIfExists('packages');
    }
};
