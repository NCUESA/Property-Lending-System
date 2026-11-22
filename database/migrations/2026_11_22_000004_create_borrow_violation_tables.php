<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 違規紀錄與借用封鎖。
 *
 * 訪客也能借用（borrow_requests.user_id 可為 null），所以不能只用帳號當 key：
 * 兩張表都同時存 student_id 與 phone，比對時任一相符就算同一個人。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrow_violations', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->comment('BorrowViolationKind：self_cancel / expired / overdue');

            // 身分：三個都留著，user_id 只是方便關聯，比對一律用 student_id 或 phone
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('student_id', 20)->nullable();
            $table->string('phone', 20);
            $table->string('borrower_name', 50);

            $table->foreignId('borrow_request_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'occurred_at']);
            $table->index(['phone', 'occurred_at']);
        });

        // 同一張單同一種違規只記一次，排程重跑也不會灌出重複紀錄
        DB::statement('
            CREATE UNIQUE INDEX borrow_violations_once_per_request
            ON borrow_violations (kind, borrow_request_id)
            WHERE borrow_request_id IS NOT NULL
        ');

        Schema::create('borrow_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 20)->nullable();
            $table->string('phone', 20);
            $table->string('borrower_name', 50);

            $table->string('reason');
            $table->timestamp('blocked_until');
            $table->foreignId('created_by')->nullable()
                ->comment('null 代表系統自動封鎖')
                ->constrained('users')->nullOnDelete();

            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lift_reason')->nullable();
            $table->timestamps();

            $table->index(['phone', 'blocked_until']);
            $table->index(['student_id', 'blocked_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrow_blocks');
        Schema::dropIfExists('borrow_violations');
    }
};
