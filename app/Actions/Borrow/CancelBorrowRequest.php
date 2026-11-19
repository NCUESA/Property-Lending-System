<?php

namespace App\Actions\Borrow;

use App\Enums\BorrowItemStatus;
use App\Models\BorrowRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 整張退回：只限尚未出借的借用單。
 */
class CancelBorrowRequest
{
    public function handle(BorrowRequest $request, User $staff, ?string $reason): BorrowRequest
    {
        return DB::transaction(function () use ($request, $staff, $reason) {
            $request = BorrowRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($request->lent_at !== null) {
                throw ValidationException::withMessages(['reason' => '已出借的借用單不能退回，請改用歸還。']);
            }

            $request->items()
                ->where('status', BorrowItemStatus::Pending)
                ->update(['status' => BorrowItemStatus::Cancelled, 'updated_at' => now()]);

            $note = sprintf('[%s 由 %s 退回]%s', now()->format('Y-m-d H:i'), $staff->name, $reason ? " {$reason}" : '');
            $request->update(['remark' => trim(($request->remark ?? '')."\n".$note)]);

            return $request;
        });
    }
}
