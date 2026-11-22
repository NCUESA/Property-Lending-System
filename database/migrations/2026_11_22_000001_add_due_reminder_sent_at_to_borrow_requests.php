<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 到期提醒信只能寄一次，用這個欄位記錄寄出時間當作去重依據。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrow_requests', function (Blueprint $table) {
            $table->timestamp('due_reminder_sent_at')->nullable()->comment('到期提醒信寄出時間');
        });
    }

    public function down(): void
    {
        Schema::table('borrow_requests', function (Blueprint $table) {
            $table->dropColumn('due_reminder_sent_at');
        });
    }
};
