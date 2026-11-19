<?php

namespace Database\Factories;

use App\Models\BorrowRequest;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BorrowRequest>
 */
class BorrowRequestFactory extends Factory
{
    public function definition(): array
    {
        $borrowDate = now()->startOfDay();

        return [
            'location_id' => Location::factory(),
            'borrower_department' => fake()->randomElement(['資工系', '熱舞社', '吉他社']),
            'borrower_name' => fake()->name(),
            'phone' => '09'.fake()->numerify('########'),
            'email' => fake()->safeEmail(),
            'borrow_date' => $borrowDate,
            'due_date' => $borrowDate->copy()->addDays(fake()->numberBetween(0, 6)),
            'agreed_terms' => true,
        ];
    }
}
