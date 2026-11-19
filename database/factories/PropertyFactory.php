<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyPool;
use App\Models\PropertySubcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ssid' => fake()->unique()->regexify('[A-Z][0-9]{3}[0-9A-Z][0-9]{3}'),
            'property_subcategory_id' => PropertySubcategory::factory(),
            'sequence_no' => fake()->numberBetween(1, 20),
            'name' => fake()->randomElement(['動圈式麥克風', '延長線', '投影機', '喇叭', '折疊桌']),
            'second_name' => null,
            'spec' => fake()->optional()->word(),
            'price' => fake()->numberBetween(100, 30000),
            'department' => '學生會',
            'custodian' => fake()->name(),
            'location_id' => Location::factory(),
            'property_pool_id' => null,
            'acquired_on' => fake()->date(),
            'is_school_owned' => false,
            'auth_level' => 0,
            'image_path' => null,
            'is_lendable' => true,
            'remark' => null,
        ];
    }

    public function notLendable(): static
    {
        return $this->state(fn () => ['is_lendable' => false]);
    }

    public function restricted(int $level = 1): static
    {
        return $this->state(fn () => ['auth_level' => $level]);
    }

    public function inPool(PropertyPool $pool): static
    {
        return $this->state(fn () => [
            'property_pool_id' => $pool->id,
            'location_id' => $pool->location_id,
        ]);
    }
}
