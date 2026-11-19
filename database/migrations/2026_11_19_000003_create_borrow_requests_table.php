<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrow_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->restrictOnDelete()->comment('取用地點');

            // 借用人（訪客填寫）
            $table->string('borrower_department', 100);
            $table->string('borrower_name', 50);
            $table->string('phone', 20);
            $table->string('email');
            $table->date('borrow_date');
            $table->date('due_date');
            $table->boolean('agreed_terms')->default(false);
            $table->string('status', 20)->default('pending');

            // 出借
            $table->foreignId('lent_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('lent_at')->nullable();
            $table->boolean('id_card_taken')->default(false);
            $table->boolean('deposit_taken')->default(false);
            $table->unsignedSmallInteger('deposit_box_no')->nullable();

            // 歸還（整單結案）
            $table->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->boolean('id_card_returned')->default(false);
            $table->boolean('deposit_returned')->default(false);

            $table->text('remark')->nullable();

            // 舊資料經手人名字對不到 users 時保留原文
            $table->string('legacy_lender_name', 50)->nullable();
            $table->string('legacy_returner_name', 50)->nullable();

            $table->timestamps(); // created_at 即送出時間

            $table->index(['status', 'borrow_date']);
        });

        DB::statement("ALTER TABLE borrow_requests ADD CONSTRAINT borrow_requests_status_check CHECK (status IN ('pending', 'lent', 'returned', 'cancelled'))");
        DB::statement('ALTER TABLE borrow_requests ADD CONSTRAINT borrow_requests_dates_check CHECK (due_date >= borrow_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('borrow_requests');
    }
};
