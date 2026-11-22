<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('loc-????'),
            'name' => fake()->unique()->city(),
            // 避開 LocationSeeder 的 J、B、3、4
            'ssid_prefix' => fake()->unique()->randomElement(array_diff([...range('A', 'Z'), ...range('0', '9')], ['J', 'B', '3', '4'])),
            'is_pickup_point' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /** 存放地點，器材由指定的取用地點借出（建立後補上 location_links 的那條邊）。 */
    public function storedAt(Location $pickup): static
    {
        return $this->state(fn () => ['is_pickup_point' => false])
            ->afterCreating(fn (Location $storage) => $pickup->storageLocations()->syncWithoutDetaching([$storage->id]));
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
