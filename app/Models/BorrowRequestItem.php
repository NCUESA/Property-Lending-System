<?php

namespace App\Models;

use App\Enums\BorrowItemStatus;
use Carbon\CarbonImmutable;
use Database\Factories\BorrowRequestItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 借用明細：指定器材（property_id）或 bulk 品項池（property_pool_id + quantity）擇一。
 *
 * @property int $id
 * @property int $borrow_request_id
 * @property int|null $property_id
 * @property int|null $property_pool_id
 * @property int $quantity
 * @property int $returned_quantity
 * @property BorrowItemStatus $status
 * @property int|null $returned_by
 * @property CarbonImmutable|null $returned_at
 */
#[Fillable([
    'borrow_request_id', 'property_id', 'property_pool_id', 'quantity', 'returned_quantity',
    'status', 'returned_by', 'returned_at',
])]
class BorrowRequestItem extends Model
{
    /** @use HasFactory<BorrowRequestItemFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
        'quantity' => 1,
        'returned_quantity' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => BorrowItemStatus::class,
            'quantity' => 'integer',
            'returned_quantity' => 'integer',
            'returned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BorrowRequest, $this> */
    public function borrowRequest(): BelongsTo
    {
        return $this->belongsTo(BorrowRequest::class);
    }

    /**
     * 顯示用的品名。
     *
     * property 與 pool 擇一，另一邊的關聯會是 null，所以一律先看外鍵再取值。
     */
    public function displayName(): string
    {
        if ($this->property_id !== null) {
            return $this->property->name;
        }

        return $this->property_pool_id !== null ? $this->pool->name : '—';
    }

    /** 指定器材才有財產編號。 */
    public function displaySsid(): ?string
    {
        return $this->property_id !== null ? $this->property->ssid : null;
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<PropertyPool, $this> */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(PropertyPool::class, 'property_pool_id');
    }

    /** @return BelongsTo<User, $this> */
    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function isBulk(): bool
    {
        return $this->property_pool_id !== null;
    }

    public function outstandingQuantity(): int
    {
        return $this->quantity - $this->returned_quantity;
    }

    /** @param Builder<BorrowRequestItem> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', BorrowItemStatus::ACTIVE);
    }
}
