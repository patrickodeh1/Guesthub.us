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
        Schema::table('instruction_steps', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->change();
            $table->foreignId('source_step_id')->nullable()->after('property_id')
                ->constrained('instruction_steps')->nullOnDelete();
        });

        $oldest = DB::table('properties')->oldest()->first();

        if ($oldest) {
            $steps = DB::table('instruction_steps')->where('property_id', $oldest->id)->get();

            foreach ($steps as $step) {
                $globalId = DB::table('instruction_steps')->insertGetId([
                    'property_id' => null,
                    'source_step_id' => null,
                    'type' => $step->type,
                    'sort_order' => $step->sort_order,
                    'title' => $step->title,
                    'content' => $step->content,
                    'image_path' => $step->image_path,
                    'active' => $step->active,
                    'hide_when_parking' => $step->hide_when_parking,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('instruction_steps')->where('id', $step->id)->update(['source_step_id' => $globalId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instruction_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_step_id');
        });
        DB::table('instruction_steps')->whereNull('property_id')->delete();
    }
};
