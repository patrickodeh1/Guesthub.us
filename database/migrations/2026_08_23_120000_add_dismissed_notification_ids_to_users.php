<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Per-admin list of notification keys (e.g. "pending_id:12",
            // "checkin_today:45") they've dismissed/marked as read. Nullable,
            // no default — existing admins start with nothing dismissed, so
            // no existing behavior changes on deploy.
            $table->json('dismissed_notification_ids')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dismissed_notification_ids');
        });
    }
};
