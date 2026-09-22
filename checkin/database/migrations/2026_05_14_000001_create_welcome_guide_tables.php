<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('address');
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('map_embed_url')->nullable();
            $table->text('map_directions_url')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->longText('welcome_intro')->nullable();
            $table->longText('checkin_instructions')->nullable();
            $table->longText('parking_instructions')->nullable();
            $table->longText('checkout_instructions')->nullable();
            $table->string('header_image')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_id')->unique();
            $table->string('guest_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('photo_id_path')->nullable();
            $table->boolean('parking_needed')->nullable();
            $table->boolean('gps_verified')->default(false);
            $table->boolean('manually_checked_in')->default(false);
            $table->timestamp('checked_in_at')->nullable();
            $table->string('status')->default('pending');
            $table->longText('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('is_global')->default(true);
            $table->timestamps();
        });

        Schema::create('property_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('custom_title')->nullable();
            $table->text('custom_description')->nullable();
            $table->string('header_image')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['property_id', 'category_id']);
        });

        Schema::create('category_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('image_1')->nullable();
            $table->string('image_2')->nullable();
            $table->string('image_3')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('icon')->nullable();
            $table->longText('details')->nullable();
            $table->json('images')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('category_pages');
        Schema::dropIfExists('property_category');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('properties');
    }
};
