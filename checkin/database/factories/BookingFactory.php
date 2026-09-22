<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'booking_id' => 'BK-' . $this->faker->unique()->randomNumber(6),
            'guest_name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'check_in_date' => now()->addDays(1)->toDateString(),
            'check_out_date' => now()->addDays(4)->toDateString(),
            'property_id' => Property::factory(),
            'token' => $this->faker->unique()->uuid(),
            'status' => 'pending',
            'pay_by_cc' => false,
        ];
    }
}
