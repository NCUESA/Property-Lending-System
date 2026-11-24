<?php

namespace App\Support;

use App\Enums\BorrowItemStatus;
use App\Models\DepartmentAlias;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * 數據頁共用的查詢。
 *
 * 「借出」只算真的拿走的明細（lent、returned），申請中、退回、未取件都不算。
 * 時間範圍一律以借用日（borrow_date）篩選。
 */
final class BorrowStats
{
    /** 數據頁共用的時間範圍驗證規則 */
    public const RANGE_RULES = [
        'from' => ['nullable', 'date'],
        'to' => ['nullable', 'date', 'after_or_equal:from'],
    ];

    /** 每件明細借出的天數（未還算到今天，同一天借還算 1 天） */
    public const ITEM_DAYS_SQL = '(coalesce(i.returned_at::date, current_date) - coalesce(r.lent_at::date, r.borrow_date) + 1)';

    /**
     * 借出過的明細（i）與所屬借用單（r）。
     *
     * @param  array{from?: ?string, to?: ?string}  $range
     */
    public static function lentItems(array $range): Builder
    {
        return self::inRange(
            DB::table('borrow_request_items as i')
                ->join('borrow_requests as r', 'r.id', '=', 'i.borrow_request_id')
                ->whereIn('i.status', [BorrowItemStatus::Lent->value, BorrowItemStatus::Returned->value]),
            $range,
        );
    }

    /**
     * 至少借出過一件的借用單（r）。
     *
     * @param  array{from?: ?string, to?: ?string}  $range
     */
    public static function lentRequests(array $range): Builder
    {
        return self::inRange(DB::table('borrow_requests as r'), $range)
            ->whereExists(fn (Builder $q) => $q->from('borrow_request_items as li')
                ->whereColumn('li.borrow_request_id', 'r.id')
                ->whereIn('li.status', [BorrowItemStatus::Lent->value, BorrowItemStatus::Returned->value]));
    }

    /** 加上統計用的單位名稱（同義詞合併後），之後可用 DepartmentAlias::CANONICAL_SQL。 */
    public static function withDepartment(Builder $query): Builder
    {
        return $query->leftJoin('department_aliases as da', fn (JoinClause $j) => $j->whereRaw(DepartmentAlias::JOIN_SQL));
    }

    /** 加上每張單借出的件數 iq.quantity（大量借用品項算數量）。 */
    public static function withItemQuantity(Builder $query): Builder
    {
        $perRequest = DB::table('borrow_request_items')
            ->whereIn('status', [BorrowItemStatus::Lent->value, BorrowItemStatus::Returned->value])
            ->selectRaw('borrow_request_id, sum(quantity) AS quantity')
            ->groupBy('borrow_request_id');

        return $query->leftJoinSub($perRequest, 'iq', 'iq.borrow_request_id', '=', 'r.id');
    }

    /** @param  array{from?: ?string, to?: ?string}  $range */
    public static function inRange(Builder $query, array $range): Builder
    {
        return $query
            ->when($range['from'] ?? null, fn (Builder $q, string $d) => $q->whereDate('r.borrow_date', '>=', $d))
            ->when($range['to'] ?? null, fn (Builder $q, string $d) => $q->whereDate('r.borrow_date', '<=', $d));
    }
}
