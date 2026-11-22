<?php

use App\Enums\BorrowViolationKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 三件事：
 *   1. 借用單新增「逾期歸還」狀態（全部還完，但超過歸還日）
 *   2. 違規的次數上限與封鎖天數改成每一種各自設定
 *   3. 封鎖可以由管理員調整期限，記下是誰調的
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->recreateView();

        // 舊的兩個鍵只管「自行取消」，搬到新的 per-kind 鍵，不讓已調過的設定消失
        $this->moveSetting('borrow.self_cancel_limit', BorrowViolationKind::SelfCancel->settingKey('limit'));
        $this->moveSetting('borrow.block_days', BorrowViolationKind::SelfCancel->settingKey('block_days'));

        foreach (BorrowViolationKind::cases() as $kind) {
            $this->seedSetting($kind->settingKey('limit'), $kind->defaultLimit());
            $this->seedSetting($kind->settingKey('block_days'), $kind->defaultBlockDays());
        }

        Schema::table('borrow_blocks', function (Blueprint $table) {
            $table->timestamp('adjusted_at')->nullable();
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('adjust_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('borrow_blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adjusted_by');
            $table->dropColumn(['adjusted_at', 'adjust_reason']);
        });

        $this->moveSetting(BorrowViolationKind::SelfCancel->settingKey('limit'), 'borrow.self_cancel_limit');
        $this->moveSetting(BorrowViolationKind::SelfCancel->settingKey('block_days'), 'borrow.block_days');

        DB::table('settings')->where('key', 'like', 'borrow.violation.%')->delete();

        $this->recreateView(withReturnedLate: false);
    }

    private function moveSetting(string $from, string $to): void
    {
        $value = DB::table('settings')->where('key', $from)->value('value');

        if ($value !== null) {
            DB::table('settings')->updateOrInsert(
                ['key' => $to],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()],
            );
            DB::table('settings')->where('key', $from)->delete();
        }
    }

    private function seedSetting(string $key, int $value): void
    {
        if (! DB::table('settings')->where('key', $key)->exists()) {
            DB::table('settings')->insert([
                'key' => $key,
                'value' => json_encode($value),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * VIEW 是 br.* 定義的，欄位清單在建立時就固定，所以每次都整個重建。
     */
    private function recreateView(bool $withReturnedLate = true): void
    {
        // 全部還完時，看整張單的結案時間有沒有超過歸還日
        $late = $withReturnedLate
            ? "WHEN br.returned_at IS NOT NULL AND br.returned_at::date > br.due_date THEN 'returned_late'"
            : '';

        DB::statement('DROP VIEW IF EXISTS borrow_request_overviews');
        DB::statement(<<<SQL
            CREATE VIEW borrow_request_overviews AS
            SELECT
                br.*,
                c.item_count,
                c.pending_count,
                c.lent_count,
                c.returned_count,
                c.cancelled_count,
                CASE
                    WHEN c.item_count = 0 OR c.cancelled_count = c.item_count THEN 'cancelled'
                    WHEN c.lent_count > 0 AND br.due_date < CURRENT_DATE THEN 'overdue'
                    WHEN c.lent_count > 0 AND (c.returned_count > 0 OR c.partial_count > 0) THEN 'partially_returned'
                    WHEN c.lent_count > 0 THEN 'lent'
                    WHEN c.pending_count > 0 AND br.borrow_date < CURRENT_DATE THEN 'expired'
                    WHEN c.pending_count > 0 THEN 'pending'
                    {$late}
                    ELSE 'returned'
                END AS status
            FROM borrow_requests br
            CROSS JOIN LATERAL (
                SELECT
                    count(*) AS item_count,
                    count(*) FILTER (WHERE i.status = 'pending') AS pending_count,
                    count(*) FILTER (WHERE i.status = 'lent') AS lent_count,
                    count(*) FILTER (WHERE i.status = 'returned') AS returned_count,
                    count(*) FILTER (WHERE i.status = 'cancelled') AS cancelled_count,
                    count(*) FILTER (WHERE i.status = 'lent' AND i.returned_quantity > 0) AS partial_count
                FROM borrow_request_items i
                WHERE i.borrow_request_id = br.id
            ) c
            SQL);
    }
};
