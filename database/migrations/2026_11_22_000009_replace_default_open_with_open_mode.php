<?php

use App\Enums\BorrowOpenMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * borrow.default_open（布林）→ borrow.open_mode（三選一）。
 *
 * 舊的布林只決定「期間外」的日期開不開；新的 always_open 更強，
 * 是連「借用期間」都不看。所以 true 會搬成 always_open，
 * 原本靠停借期間擋寒暑假的站台要改選 periods。借用期間那一頁會顯示警告提醒。
 */
return new class extends Migration
{
    public function up(): void
    {
        $value = DB::table('settings')->where('key', 'borrow.default_open')->value('value');
        $defaultOpen = $value === null ? true : (bool) json_decode((string) $value, true);

        DB::table('settings')->updateOrInsert(
            ['key' => 'borrow.open_mode'],
            [
                'value' => json_encode(
                    ($defaultOpen ? BorrowOpenMode::AlwaysOpen : BorrowOpenMode::Periods)->value
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('settings')->where('key', 'borrow.default_open')->delete();
    }

    public function down(): void
    {
        $value = DB::table('settings')->where('key', 'borrow.open_mode')->value('value');
        $mode = json_decode((string) $value, true);

        DB::table('settings')->updateOrInsert(
            ['key' => 'borrow.default_open'],
            [
                'value' => json_encode($mode === BorrowOpenMode::AlwaysOpen->value),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('settings')->where('key', 'borrow.open_mode')->delete();
    }
};
