<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a category page on one property act as the shared source for the same
 * category on other properties. A page with linked_page_id set renders the
 * linked page's content instead of its own, so a host with many units in one
 * building writes the guide once and every linked unit stays in sync (while
 * still being able to keep its own version of a page such as Wi-Fi). Deleting
 * the source simply drops the links (linked_page_id -> null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_pages', function (Blueprint $table) {
            $table->foreignId('linked_page_id')
                ->nullable()
                ->after('category_id')
                ->constrained('category_pages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('category_pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linked_page_id');
        });
    }
};
