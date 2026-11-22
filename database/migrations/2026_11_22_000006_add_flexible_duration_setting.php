<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 彈性借用：開啟時一律套用最長借用天數，關閉時由借用人自選歸還日（超過規定只在借用單上示警）。
 * 預設開啟，維持「最長一個禮拜」的規定。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('settings')->where('key', 'borrow.flexible_duration')->exists()) {
            DB::table('settings')->insert([
                'key' => 'borrow.flexible_duration',
                'value' => json_encode(true),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'borrow.flexible_duration')->delete();
    }
};
