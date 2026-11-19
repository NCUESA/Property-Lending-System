<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 借用單狀態以 VIEW 即時計算，時間一到（例如過了歸還日）狀態就會自動改變，不需要排程去更新。
 * 注意：VIEW 用 br.* 展開欄位，之後 borrow_requests 加欄位時要重建這個 VIEW。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
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

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS borrow_request_overviews');
    }
};
