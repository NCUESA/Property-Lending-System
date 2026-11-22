<?php

namespace App\Actions\Borrow;

use App\Models\BorrowRequest;
use App\Models\User;

/**
 * 展延歸還日。只保留最近一次的理由與經手人。
 */
class ExtendBorrowRequest
{
    public function handle(BorrowRequest $request, User $staff, string $dueDate, string $reason): BorrowRequest
    {
        $request->forceFill([
            'due_date' => $dueDate,
            'extension_reason' => $reason,
            'extended_at' => now(),
            'extended_by' => $staff->id,
            // 歸還日往後移了，提醒信要能依新的日期重新寄一次
            'due_reminder_sent_at' => null,
        ])->save();

        return $request;
    }
}
