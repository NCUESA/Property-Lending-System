<?php

use App\Actions\Borrow\ExpirePendingItems;
use App\Actions\Borrow\SendDueReminders;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('borrow:expire-pending', function (ExpirePendingItems $action) {
    $count = $action->handle();
    $this->info("已退回 {$count} 筆逾期未取的申請明細。");
})->purpose('退回借用日已過仍未取件的申請，釋出器材');

Artisan::command('borrow:send-due-reminders', function (SendDueReminders $action) {
    $count = $action->handle();
    $this->info("已排入 {$count} 封歸還提醒信。");
})->purpose('寄出歸還到期提醒信（天數與開關在後台設定）');

Schedule::command('borrow:expire-pending')->dailyAt('00:05');
Schedule::command('borrow:send-due-reminders')->dailyAt('09:00');
