<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('approval_status')->default('approved')->after('is_active'); // pending|approved|rejected
            $t->string('requested_role')->nullable()->after('approval_status');
        });
    }
    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['approval_status', 'requested_role']));
    }
};
