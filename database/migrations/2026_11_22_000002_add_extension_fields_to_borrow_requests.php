<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 展延歸還日時要留下理由與經手人。只保留最近一次展延的紀錄。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrow_requests', function (Blueprint $table) {
            $table->text('extension_reason')->nullable()->comment('最近一次展延的理由');
            $table->timestamp('extended_at')->nullable()->comment('最近一次展延的時間');
            $table->foreignId('extended_by')->nullable()->comment('最近一次展延的經手人')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('borrow_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('extended_by');
            $table->dropColumn(['extension_reason', 'extended_at']);
        });
    }
};
