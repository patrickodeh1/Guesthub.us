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
        $globalSteps = DB::table('instruction_steps')->whereNull('property_id')->orderBy('sort_order')->get();

        $propertiesWithSteps = DB::table('instruction_steps')
            ->whereNotNull('property_id')
            ->distinct()
            ->pluck('property_id');

        $emptyProperties = DB::table('properties')
            ->whereNotIn('id', $propertiesWithSteps)
            ->pluck('id');

        foreach ($emptyProperties as $propertyId) {
            foreach ($globalSteps as $globalStep) {
                DB::table('instruction_steps')->insert([
                    'property_id' => $propertyId,
                    'source_step_id' => $globalStep->id,
                    'type' => $globalStep->type,
                    'sort_order' => $globalStep->sort_order,
                    'title' => $globalStep->title,
                    'content' => $globalStep->content,
                    'image_path' => $globalStep->image_path,
                    'active' => $globalStep->active,
                    'hide_when_parking' => $globalStep->hide_when_parking,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
