<?php

namespace App\Actions\Borrow;

use App\Enums\BorrowRequestStatus;
use App\Enums\BorrowViolationKind;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestOverview;

/**
 * 把「超過歸還日仍有器材未還」記成違規。由排程每天執行。
 *
 * 逾期是 VIEW 即時算出來的狀態、不是一次性事件，所以每天都會重新掃到同一張單；
 * RecordBorrowViolation 用資料庫的 unique index 保證每張單只留下一筆。
 */
class RecordOverdueViolations
{
    public function __construct(private readonly RecordBorrowViolation $violations) {}

    public function handle(): int
    {
        $overdue = BorrowRequestOverview::query()
            ->whereStatus(BorrowRequestStatus::Overdue)
            ->pluck('id');

        $recorded = 0;

        foreach (BorrowRequest::query()->whereIn('id', $overdue)->with('borrower:id,student_id')->get() as $request) {
            $violation = $this->violations->handle(
                BorrowViolationKind::Overdue,
                $request,
                "應於 {$request->due_date->toDateString()} 歸還",
            );

            $recorded += $violation->wasRecentlyCreated ? 1 : 0;
        }

        return $recorded;
    }
}
