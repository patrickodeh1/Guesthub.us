<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instruction_steps', function (Blueprint $table) {
            if (! Schema::hasColumn('instruction_steps', 'for_guest_parking')) {
                $table->boolean('for_guest_parking')->default(true);
            }
            if (! Schema::hasColumn('instruction_steps', 'for_guest_no_parking')) {
                $table->boolean('for_guest_no_parking')->default(true);
            }
            if (! Schema::hasColumn('instruction_steps', 'for_cleaner')) {
                $table->boolean('for_cleaner')->default(true);
            }
        });

        $t = fn () => DB::table('instruction_steps');
        // null / 'all' keep the all-true defaults.
        $t()->where('visibility', 'parkers_only')->update(['for_guest_parking' => true, 'for_guest_no_parking' => false, 'for_cleaner' => false]);
        $t()->where('visibility', 'cleaners_only')->update(['for_guest_parking' => false, 'for_guest_no_parking' => false, 'for_cleaner' => true]);
        $t()->where('visibility', 'guests_only')->update(['for_guest_parking' => true, 'for_guest_no_parking' => true, 'for_cleaner' => false]);
        // Today cleaners get "guests without parking" check-in steps but not checkout ones; preserve that.
        $t()->where('visibility', 'non_parkers_only')->where('type', '!=', 'checkout')
            ->update(['for_guest_parking' => false, 'for_guest_no_parking' => true, 'for_cleaner' => true]);
        $t()->where('visibility', 'non_parkers_only')->where('type', 'checkout')
            ->update(['for_guest_parking' => false, 'for_guest_no_parking' => true, 'for_cleaner' => false]);
        // Legacy standalone parking-type rows never reached cleaners.
        $t()->where('type', 'parking')->update(['for_cleaner' => false]);
    }

    public function down(): void
    {
        Schema::table('instruction_steps', function (Blueprint $table) {
            $table->dropColumn(['for_guest_parking', 'for_guest_no_parking', 'for_cleaner']);
        });
    }
};
