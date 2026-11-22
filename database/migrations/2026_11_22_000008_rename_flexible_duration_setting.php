<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * borrow.flexible_duration → borrow.fixed_duration。
 *
 * 值的意義不變（true = 一律套用規定天數），只是原本的名字和行為相反：
 * 叫「彈性借用」卻是開啟時最不彈性。改成 fixed_duration 之後名實相符。
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename('borrow.flexible_duration', 'borrow.fixed_duration');
    }

    public function down(): void
    {
        $this->rename('borrow.fixed_duration', 'borrow.flexible_duration');
    }

    private function rename(string $from, string $to): void
    {
        $value = DB::table('settings')->where('key', $from)->value('value');

        if ($value === null) {
            return;
        }

        DB::table('settings')->updateOrInsert(
            ['key' => $to],
            ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('settings')->where('key', $from)->delete();
    }
};
