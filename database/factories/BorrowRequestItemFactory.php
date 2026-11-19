<?php

namespace Database\Factories;

use App\Enums\BorrowItemStatus;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BorrowRequestItem>
 */
class BorrowRequestItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'borrow_request_id' => BorrowRequest::factory(),
            'property_id' => Property::factory(),
            'status' => BorrowItemStatus::Pending,
        ];
    }
}
