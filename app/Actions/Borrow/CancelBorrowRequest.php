<?php

namespace App\Actions\Borrow;

use App\Enums\BorrowItemStatus;
use App\Enums\BorrowViolationKind;
use App\Models\BorrowRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 整張退回：只限尚未出借的借用單。
 *
 * 值班人員退回與借用人自行取消走同一套流程，差別在備註的寫法，
 * 以及自行取消會留下一筆違規紀錄（可能因此被封鎖）。
 */
class CancelBorrowRequest
{
    public function __construct(private readonly RecordBorrowViolation $violations) {}

    public function handle(BorrowRequest $request, User $staff, ?string $reason): BorrowRequest
    {
        return $this->cancel($request, "由 {$staff->name} 退回", $reason);
    }

    /** 借用人在「我的借用」自行取消。 */
    public function byBorrower(BorrowRequest $request, User $borrower, ?string $reason): BorrowRequest
    {
        if ($request->user_id !== $borrower->id) {
            throw ValidationException::withMessages(['reason' => '只能取消自己的借用申請。']);
        }

        $cancelled = $this->cancel($request, '由借用人自行取消', $reason);

        $this->violations->handle(BorrowViolationKind::SelfCancel, $cancelled, $reason);

        return $cancelled;
    }

    private function cancel(BorrowRequest $request, string $who, ?string $reason): BorrowRequest
    {
        return DB::transaction(function () use ($request, $who, $reason) {
            $request = BorrowRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($request->lent_at !== null) {
                throw ValidationException::withMessages(['reason' => '已出借的借用單不能退回，請改用歸還。']);
            }

            $cancelled = $request->items()
                ->where('status', BorrowItemStatus::Pending)
                ->update(['status' => BorrowItemStatus::Cancelled, 'updated_at' => now()]);

            if ($cancelled === 0) {
                throw ValidationException::withMessages(['reason' => '這張單沒有可以退回的品項。']);
            }

            $note = sprintf('[%s %s]%s', now()->format('Y-m-d H:i'), $who, $reason ? " {$reason}" : '');
            $request->update(['remark' => trim(($request->remark ?? '')."\n".$note)]);

            return $request;
        });
    }
}
