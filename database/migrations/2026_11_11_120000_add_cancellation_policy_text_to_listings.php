<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private function table(): string
    {
        return (new \App\Models\PropertyListing())->getTable();
    }

    public function up(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            $table->text('cancellation_policy_text')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            $table->dropColumn('cancellation_policy_text');
        });
    }
};
