<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\PropertyPool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyPool>
 */
class PropertyPoolFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['折疊椅', '塑膠椅', '長桌', '三角錐', '紅龍柱']),
            'location_id' => Location::factory(),
            'auth_level' => 0,
        ];
    }
}
