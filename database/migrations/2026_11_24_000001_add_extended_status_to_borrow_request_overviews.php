<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 狀態加上「展延中」（extended）：展延過且還有器材借出、尚未逾期。
 * 展延後又逾期仍是 overdue；全部還完就回到 returned / returned_late。
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->recreateView(withExtended: true);
    }

    public function down(): void
    {
        $this->recreateView(withExtended: false);
    }

    private function recreateView(bool $withExtended): void
    {
        $extended = $withExtended
            ? "WHEN c.lent_count > 0 AND br.extended_at IS NOT NULL THEN 'extended'"
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
                    {$extended}
                    WHEN c.lent_count > 0 AND (c.returned_count > 0 OR c.partial_count > 0) THEN 'partially_returned'
                    WHEN c.lent_count > 0 THEN 'lent'
                    WHEN c.pending_count > 0 AND br.borrow_date < CURRENT_DATE THEN 'expired'
                    WHEN c.pending_count > 0 THEN 'pending'
                    WHEN br.returned_at IS NOT NULL AND br.returned_at::date > br.due_date THEN 'returned_late'
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
