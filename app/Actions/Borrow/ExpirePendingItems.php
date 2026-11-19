<?php

namespace App\Actions\Borrow;

use App\Enums\BorrowItemStatus;
use App\Models\BorrowRequestItem;

/**
 * 借用日已過仍未取件的申請改為退回，釋出被佔住的器材。由排程每天執行。
 */
class ExpirePendingItems
{
    public function handle(): int
    {
        return BorrowRequestItem::query()
            ->where('status', BorrowItemStatus::Pending)
            ->whereHas('borrowRequest', fn ($q) => $q->whereDate('borrow_date', '<', today()))
            ->update(['status' => BorrowItemStatus::Cancelled, 'updated_at' => now()]);
    }
}
