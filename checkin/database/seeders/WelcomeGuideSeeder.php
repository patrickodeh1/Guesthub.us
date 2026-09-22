<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Category;
use App\Models\CategoryPage;
use App\Models\Property;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WelcomeGuideSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Site Admin', 'password' => Hash::make('password')]
        );

        $property = Property::updateOrCreate(
            ['slug' => 'lumina-hotel-residences'],
            [
                'name' => 'Lumina Hotel & Residences',
                'address' => '123 Aura Way',
                'city' => 'San Diego',
                'state' => 'CA',
                'zip' => '92101',
                'latitude' => 32.715736,
                'longitude' => -117.161087,
                'map_directions_url' => 'https://www.google.com/maps/search/?api=1&query=123+Aura+Way+San+Diego+CA+92101',
                'map_embed_url' => 'https://www.google.com/maps?q=San%20Diego%20CA&output=embed',
                'contact_phone' => '+1 555 123 4567',
                'contact_email' => 'guestservices@example.com',
                'welcome_intro' => 'Welcome to Lumina Hotel & Residences. We are delighted to host you in downtown San Diego with a calm arrival experience and a curated local guide.',
                'checkin_instructions' => "Your smart lock code is 4826#. Enter through the main lobby doors, take the elevator to your floor, and use the code at your suite door.\n\nQuiet hours begin at 10:00 PM. Guest Services is available by phone if you need arrival support.",
                'parking_instructions' => "Parking is available in the resident garage on Level B1. Use code 4826# at the garage keypad and park only in spaces marked Visitor.",
                'checkout_instructions' => "Checkout is at 11:00 AM. Please place used towels in the bathroom, load dishes into the dishwasher, turn off lights, and close the door firmly behind you.",
                'active' => true,
            ]
        );

        $categoryData = [
            ['WiFi', 'WiFi', 'Network name, password, and connection tips.'],
            ['Amenities', 'Ameni', 'Pool, laundry, building access, and services.'],
            ['Fitness Center', 'Fit', 'Hours, access, and equipment notes.'],
            ['Pool', 'Pool', 'Pool access, rules, and towel details.'],
            ['Restaurants', 'Food', 'Favorite nearby dining options.'],
            ['Bars', 'Bars', 'Relaxed local bars and lounges.'],
            ['Parking', 'Park', 'Garage access and parking rules.'],
            ['Checkout Instructions', 'Out', 'Departure steps and reminders.'],
            ['Contact / Guest Services', 'Help', 'How to reach the team.'],
        ];

        $categories = collect($categoryData)->map(function ($item, $index) {
            return Category::updateOrCreate(
                ['slug' => Str::slug($item[0])],
                ['title' => $item[0], 'icon' => $item[1], 'description' => $item[2], 'sort_order' => $index + 1, 'active' => true, 'is_global' => true]
            );
        });

        $property->categories()->sync($categories->pluck('id')->mapWithKeys(fn ($id) => [$id => ['active' => true]])->all());

        foreach ($categories as $category) {
            CategoryPage::updateOrCreate(
                ['property_id' => $property->id, 'category_id' => $category->id],
                [
                    'title' => $category->title,
                    'content' => $this->contentFor($category->slug),
                    'sort_order' => $category->sort_order,
                    'active' => true,
                ]
            );
        }

        foreach ([
            ['Fitness Center', 'Fit', 'Open daily from 6:00 AM to 10:00 PM. Use your room code at the amenity door.'],
            ['Pool', 'Pool', 'Open 8:00 AM to 9:00 PM. Please use the pool towels from the amenity cabinet.'],
            ['Laundry', 'Wash', 'Laundry room is on Level 3 near the east elevator. Machines accept card payment.'],
            ['Parking', 'Park', 'Visitor parking is available on garage Level B1 in marked spaces only.'],
            ['Smart Lock', 'Lock', 'Use your assigned access code followed by #. The lock relocks automatically.'],
            ['Building Access', 'Door', 'Lobby and elevator access use the same code as your suite.'],
        ] as $amenity) {
            Amenity::updateOrCreate(
                ['property_id' => $property->id, 'title' => $amenity[0]],
                ['icon' => $amenity[1], 'details' => $amenity[2], 'active' => true]
            );
        }

        Booking::updateOrCreate(
            ['booking_id' => 'LUMINA-DEMO'],
            [
                'guest_name' => 'Jordan Taylor',
                'phone' => '+1 555 555 0199',
                'email' => null,
                'check_in_date' => now()->toDateString(),
                'check_out_date' => now()->addDays(3)->toDateString(),
                'property_id' => $property->id,
                'token' => 'lumina-demo-secure-token',
                'parking_needed' => null,
                'status' => 'pending',
                'notes' => 'Demo booking for client walkthrough.',
            ]
        );

        ActivityLog::updateOrCreate(
            ['action' => 'demo_created', 'description' => 'Demo portal seeded for Lumina Hotel & Residences.'],
            ['icon' => 'sparkles']
        );
        ActivityLog::updateOrCreate(
            ['action' => 'property_ready', 'description' => 'Lumina property content and guide categories are ready for review.'],
            ['icon' => 'properties', 'subject_type' => Property::class, 'subject_id' => $property->id]
        );
        ActivityLog::updateOrCreate(
            ['action' => 'guest_url_ready', 'description' => 'Demo guest secure check-in URL is ready to copy.'],
            ['icon' => 'copy']
        );

        foreach ([
            'gps_radius_meters' => 150,
            'brand_color' => '#0f766e',
            'contact_phone' => '+1 555 123 4567',
            'contact_email' => 'guestservices@example.com',
            'default_intro' => 'Welcome. Your arrival details and local guide are ready when you are.',
        ] as $key => $value) {
            Setting::putValue($key, $value);
        }
    }

    private function contentFor(string $slug): string
    {
        return match ($slug) {
            'wifi' => "Network: Lumina Guest\nPassword: StayBright123\n\nFor the fastest connection, choose the 5G network when available.",
            'restaurants' => "Juniper & Ivy is excellent for a polished dinner. The Fish Market is a reliable waterfront choice, and Morning Glory is a popular brunch stop.",
            'bars' => "Try The Nolen for rooftop views, False Idol for a memorable cocktail room, and Neighborhood for an easy downtown evening.",
            'parking' => "Use the B1 visitor garage and keep your vehicle in marked visitor spaces. Oversized vehicles should contact Guest Services before arrival.",
            'checkout-instructions' => "Checkout is at 11:00 AM. Please gather belongings, place used towels in the bathroom, and close the suite door firmly.",
            'contact-guest-services' => "Guest Services\nPhone: +1 555 123 4567\nEmail: guestservices@example.com\n\nFor urgent issues, call rather than email.",
            default => "This section includes practical details for your stay at Lumina Hotel & Residences. Admins can edit this content, upload images, and tailor it for each property.",
        };
    }
}
