<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $globalSteps = DB::table('instruction_steps')
            ->whereNull('property_id')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('type');

        $propertySteps = DB::table('instruction_steps')
            ->whereNotNull('property_id')
            ->whereNull('source_step_id')
            ->orderBy('property_id')
            ->orderBy('sort_order')
            ->get()
            ->groupBy(['property_id', 'type']);

        foreach ($propertySteps as $propertyId => $byType) {
            foreach ($byType as $type => $steps) {
                $globalForType = ($globalSteps->get($type) ?? collect())->values();
                foreach ($steps->values() as $index => $step) {
                    $global = $globalForType->get($index);
                    if ($global) {
                        DB::table('instruction_steps')->where('id', $step->id)->update(['source_step_id' => $global->id]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('instruction_steps')->whereNotNull('property_id')->update(['source_step_id' => null]);
    }
};
