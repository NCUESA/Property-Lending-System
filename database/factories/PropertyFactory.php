<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ssid' => fake()->unique()->bothify('J#######'),
            'sequence_no' => fake()->numberBetween(1, 20),
            'category' => fake()->randomElement(['什項用具', '資訊設備', '音響設備']),
            'name' => fake()->randomElement(['動圈式麥克風', '延長線', '投影機', '喇叭', '折疊桌']),
            'second_name' => null,
            'spec' => fake()->optional()->word(),
            'price' => fake()->numberBetween(100, 30000),
            'department' => '學生會',
            'custodian' => fake()->name(),
            'location_id' => Location::factory(),
            'acquired_on' => fake()->date(),
            'is_school_owned' => false,
            'image_path' => null,
            'is_lendable' => true,
            'remark' => null,
        ];
    }

    public function notLendable(): static
    {
        return $this->state(fn () => ['is_lendable' => false]);
    }
}
