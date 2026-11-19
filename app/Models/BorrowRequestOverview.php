<?php

namespace App\Models;

use App\Enums\BorrowRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 唯讀：對應 VIEW borrow_request_overviews，借用單所有欄位加上即時計算的狀態。
 * 借用列表用它查詢、篩選、分頁；要寫入請用 BorrowRequest。
 *
 * @property BorrowRequestStatus $status
 * @property int $item_count
 * @property int $pending_count
 * @property int $lent_count
 * @property int $returned_count
 * @property int $cancelled_count
 */
class BorrowRequestOverview extends BorrowRequest
{
    protected $table = 'borrow_request_overviews';

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'status' => BorrowRequestStatus::class,
            'item_count' => 'integer',
            'pending_count' => 'integer',
            'lent_count' => 'integer',
            'returned_count' => 'integer',
            'cancelled_count' => 'integer',
        ];
    }

    /** @return HasMany<BorrowRequestItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BorrowRequestItem::class, 'borrow_request_id');
    }

    /** @return BelongsTo<BorrowRequest, $this> */
    public function borrowRequest(): BelongsTo
    {
        return $this->belongsTo(BorrowRequest::class, 'id');
    }

    /**
     * @param  Builder<BorrowRequestOverview>  $query
     * @param  BorrowRequestStatus|list<BorrowRequestStatus>  $status
     */
    public function scopeWhereStatus(Builder $query, BorrowRequestStatus|array $status): void
    {
        $values = array_map(fn (BorrowRequestStatus $s) => $s->value, is_array($status) ? $status : [$status]);
        $query->whereIn('status', $values);
    }

    public function save(array $options = []): bool
    {
        throw new \LogicException('BorrowRequestOverview 是唯讀的 VIEW，請改用 BorrowRequest 寫入。');
    }

    public function delete(): ?bool
    {
        throw new \LogicException('BorrowRequestOverview 是唯讀的 VIEW，請改用 BorrowRequest 刪除。');
    }
}
