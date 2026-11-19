<?php

namespace Database\Factories;

use App\Enums\BorrowItemStatus;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;
use App\Models\Property;
use App\Models\PropertyPool;
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

    /** bulk 明細：從品項池借 $quantity 件。 */
    public function fromPool(PropertyPool $pool, int $quantity): static
    {
        return $this->state(fn () => [
            'property_id' => null,
            'property_pool_id' => $pool->id,
            'quantity' => $quantity,
        ]);
    }
}
