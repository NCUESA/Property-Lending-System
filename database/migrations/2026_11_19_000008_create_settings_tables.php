<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 單一數值的設定：押金金額、最長借用天數、注意事項……
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->jsonb('value')->nullable();
            $table->timestamps();
        });

        // 開放／暫停借用期間（例如寒暑假）。不在任何期間內的日期依 settings 的 borrow.default_open 決定。
        Schema::create('borrow_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_open');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE borrow_periods ADD CONSTRAINT borrow_periods_dates_check CHECK (ends_on >= starts_on)');
        // 期間不能重疊，避免同一天同時「開放」又「暫停」
        DB::statement("ALTER TABLE borrow_periods ADD CONSTRAINT borrow_periods_no_overlap EXCLUDE USING gist (daterange(starts_on, ends_on, '[]') WITH &&)");
    }

    public function down(): void
    {
        Schema::dropIfExists('borrow_periods');
        Schema::dropIfExists('settings');
    }
};
