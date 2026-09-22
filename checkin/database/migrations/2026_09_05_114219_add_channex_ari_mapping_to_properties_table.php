<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Channex's ARI (Availability/Rates/Inventory) endpoints are scoped by
     * room_type_id + rate_plan_id, NOT by property_id alone -- these are
     * additional identifiers Channex assigns per property, distinct from
     * channex_property_id which only identifies the property itself.
     * Nullable because existing properties won't have these set until an
     * admin fetches/selects them via the new availability page.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // channex_rate_plan_id deliberately not added -- Guesthub never
            // pushes rates (PriceLabs pushes rates directly into Channex),
            // so rate plan mapping has no use here. Only room_type_id is
            // needed, for availability pushes.
            $table->string('channex_room_type_id')->nullable()->after('channex_property_id');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('channex_room_type_id');
        });
    }
};
