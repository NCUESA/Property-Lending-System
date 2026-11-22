<?php

use App\Actions\Borrow\ExpirePendingItems;
use App\Actions\Borrow\RecordOverdueViolations;
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

Artisan::command('borrow:record-overdue', function (RecordOverdueViolations $action) {
    $count = $action->handle();
    $this->info("已記下 {$count} 筆新的逾期未還違規。");
})->purpose('把逾期未還的借用單記成違規紀錄');

Schedule::command('borrow:expire-pending')->dailyAt('00:05');
Schedule::command('borrow:record-overdue')->dailyAt('00:10');
Schedule::command('borrow:send-due-reminders')->dailyAt('09:00');
