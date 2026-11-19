<?php

namespace App\Models;

use App\Enums\BorrowItemStatus;
use Database\Factories\BorrowRequestItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $borrow_request_id
 * @property int $property_id
 * @property BorrowItemStatus $status
 * @property int|null $returned_by
 * @property Carbon|null $returned_at
 */
#[Fillable(['borrow_request_id', 'property_id', 'status', 'returned_by', 'returned_at'])]
class BorrowRequestItem extends Model
{
    /** @use HasFactory<BorrowRequestItemFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => BorrowItemStatus::class,
            'returned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BorrowRequest, $this> */
    public function borrowRequest(): BelongsTo
    {
        return $this->belongsTo(BorrowRequest::class);
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

    /** @param Builder<BorrowRequestItem> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', BorrowItemStatus::ACTIVE);
    }
}
