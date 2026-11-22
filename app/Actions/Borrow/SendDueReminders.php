<?php

namespace App\Actions\Borrow;

use App\Enums\BorrowItemStatus;
use App\Enums\BorrowMailKind;
use App\Jobs\SendBorrowNotification;
use App\Models\BorrowRequest;
use App\Models\Setting;
use App\Support\MailSettings;

/**
 * 寄出歸還到期提醒。由排程每天執行一次。
 *
 * 只挑「還有器材在外面」的單子：已全數歸還或根本還沒取件的都不需要提醒。
 * due_reminder_sent_at 當作去重依據，所以同一天跑兩次也不會重複寄。
 */
class SendDueReminders
{
    public const DAYS_BEFORE_KEY = 'notify.due.days_before';

    public const DEFAULT_DAYS_BEFORE = 2;

    public function handle(): int
    {
        if (! MailSettings::enabled() || ! (bool) Setting::get(BorrowMailKind::DueReminder->key('enabled'), false)) {
            return 0;
        }

        $daysBefore = (int) Setting::get(self::DAYS_BEFORE_KEY, self::DEFAULT_DAYS_BEFORE);

        $requests = BorrowRequest::query()
            ->whereNull('due_reminder_sent_at')
            ->whereDate('due_date', today()->addDays($daysBefore))
            ->whereHas('items', fn ($q) => $q->where('status', BorrowItemStatus::Lent))
            ->get();

        foreach ($requests as $request) {
            SendBorrowNotification::dispatch($request, BorrowMailKind::DueReminder);
            $request->forceFill(['due_reminder_sent_at' => now()])->save();
        }

        return $requests->count();
    }
}
