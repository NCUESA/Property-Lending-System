<?php

use App\Actions\Borrow\ExpirePendingItems;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('borrow:expire-pending', function (ExpirePendingItems $action) {
    $count = $action->handle();
    $this->info("已退回 {$count} 筆逾期未取的申請明細。");
})->purpose('退回借用日已過仍未取件的申請，釋出器材');

Schedule::command('borrow:expire-pending')->dailyAt('00:05');
