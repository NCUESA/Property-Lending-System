<?php

namespace App\Actions\Borrow;

use App\Enums\BorrowItemStatus;
use App\Enums\BorrowViolationKind;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;

/**
 * 借用日已過仍未取件的申請改為退回，釋出被佔住的器材，並記一筆違規。由排程每天執行。
 */
class ExpirePendingItems
{
    public function __construct(private readonly RecordBorrowViolation $violations) {}

    public function handle(): int
    {
        // 先把受影響的單撈起來：update 之後就查不到還有哪些是 pending 了
        $requests = BorrowRequest::query()
            ->whereDate('borrow_date', '<', today())
            ->whereHas('items', fn ($q) => $q->where('status', BorrowItemStatus::Pending))
            ->with('borrower:id,student_id')
            ->get();

        $expired = BorrowRequestItem::query()
            ->where('status', BorrowItemStatus::Pending)
            ->whereIn('borrow_request_id', $requests->pluck('id'))
            ->update(['status' => BorrowItemStatus::Cancelled, 'updated_at' => now()]);

        foreach ($requests as $request) {
            $this->violations->handle(
                BorrowViolationKind::Expired,
                $request,
                "借用日 {$request->borrow_date->toDateString()} 未取件",
            );
        }

        return $expired;
    }
}
