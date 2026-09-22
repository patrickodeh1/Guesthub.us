<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company() . ' House';

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name) . '-' . $this->faker->unique()->randomNumber(5),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'active' => true,
        ];
    }
}
