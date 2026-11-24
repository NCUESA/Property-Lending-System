<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 大量借用品項借出的單件器材。
 *
 * @property int $id
 * @property int $borrow_request_item_id
 * @property int $property_id
 * @property CarbonImmutable|null $returned_at
 * @property int|null $returned_by
 * @property CarbonImmutable $created_at
 */
#[Fillable(['borrow_request_item_id', 'property_id', 'returned_at', 'returned_by'])]
class BorrowItemUnit extends Model
{
    protected function casts(): array
    {
        return [
            'returned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BorrowRequestItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(BorrowRequestItem::class, 'borrow_request_item_id');
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<User, $this> */
    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /** @param  Builder<BorrowItemUnit>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('returned_at');
    }
}
