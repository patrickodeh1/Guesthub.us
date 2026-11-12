<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $t) {
            $t->longText('rental_agreement')->nullable();
            $t->boolean('skip_rental_agreement')->default(false);
        });
        Schema::table('bookings', function (Blueprint $t) {
            $t->longText('contract_snapshot')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('properties', fn (Blueprint $t) => $t->dropColumn(['rental_agreement', 'skip_rental_agreement']));
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn('contract_snapshot'));
    }
};
