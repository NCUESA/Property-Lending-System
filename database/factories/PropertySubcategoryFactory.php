<?php

namespace Database\Factories;

use App\Models\PropertyCategory;
use App\Models\PropertySubcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertySubcategory>
 */
class PropertySubcategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_category_id' => PropertyCategory::factory(),
            'code' => '0',
            'name' => fake()->word(),
        ];
    }
}
