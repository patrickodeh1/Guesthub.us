<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('owner')->after('name');
            $table->string('status')->default('active')->after('role');
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar')->nullable()->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('admin_tour_completed_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->timestamp('dashboard_tour_completed_at')->nullable()->after('last_login_ip');
            $table->timestamp('full_system_tour_completed_at')->nullable()->after('dashboard_tour_completed_at');
            $table->unsignedBigInteger('created_by')->nullable()->after('full_system_tour_completed_at');
            $table->text('notes')->nullable()->after('created_by');
            $table->index('role');
            $table->index('status');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('actor_type')->default('admin')->after('id');
            $table->unsignedBigInteger('actor_id')->nullable()->after('actor_type');
            $table->string('actor_name')->nullable()->after('actor_id');
            $table->string('actor_email')->nullable()->after('actor_name');
            $table->string('module')->nullable()->after('icon');
            $table->string('severity')->default('info')->after('module');
            $table->string('ip_address', 45)->nullable()->after('severity');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->json('metadata')->nullable()->after('user_agent');
            $table->json('old_values')->nullable()->after('metadata');
            $table->json('new_values')->nullable()->after('old_values');
            $table->unsignedBigInteger('property_id')->nullable()->after('new_values');
            $table->unsignedBigInteger('booking_id')->nullable()->after('property_id');
            $table->index(['actor_type', 'actor_id'], 'al_actor_idx');
            $table->index('module', 'al_module_idx');
            $table->index('severity', 'al_severity_idx');
            $table->index('property_id', 'al_property_idx');
            $table->index('booking_id', 'al_booking_idx');
            $table->index('created_at', 'al_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropColumn(['role', 'status', 'phone', 'avatar', 'last_login_at', 'last_login_ip', 'dashboard_tour_completed_at', 'full_system_tour_completed_at', 'created_by', 'notes']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('al_actor_idx');
            $table->dropIndex('al_module_idx');
            $table->dropIndex('al_severity_idx');
            $table->dropIndex('al_property_idx');
            $table->dropIndex('al_booking_idx');
            $table->dropIndex('al_created_idx');
            $table->dropColumn(['actor_type', 'actor_id', 'actor_name', 'actor_email', 'module', 'severity', 'ip_address', 'user_agent', 'metadata', 'old_values', 'new_values', 'property_id', 'booking_id']);
        });
    }
};
