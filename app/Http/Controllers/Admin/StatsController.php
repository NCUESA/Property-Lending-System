<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BorrowRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\DepartmentAlias;
use App\Support\BorrowStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 數據：熱門器材、常借單位、逾期排行、借用量熱力圖。
 * 單位名稱一律經過同義詞合併（DepartmentAlias）。
 */
class StatsController extends Controller
{
    private const LIMIT = 50;

    /** 熱門清單可以看的層級 */
    private const LEVELS = ['category', 'subcategory', 'property'];

    public function popular(Request $request): Response
    {
        $filters = $request->validate([
            ...BorrowStats::RANGE_RULES,
            'level' => ['nullable', Rule::in(self::LEVELS)],
        ]);
        $level = $request->string('level', 'subcategory')->toString();

        [$key, $label, $detail] = match ($level) {
            'category' => ['c.id', "c.code || ' ' || c.name", "''"],
            'property' => ['p.id', 'p.name', "p.ssid || ' · ' || c.name || ' / ' || s.name"],
            default => ['s.id', 's.name', "c.code || '-' || s.code || ' ' || c.name"],
        };

        $metrics = fn ($q) => $q
            ->selectRaw('count(DISTINCT r.id) AS times')
            ->selectRaw('sum(i.quantity) AS quantity')
            ->selectRaw('sum(i.quantity * '.BorrowStats::ITEM_DAYS_SQL.') AS item_days');

        $serial = $metrics(BorrowStats::lentItems($filters)
            ->join('properties as p', 'p.id', '=', 'i.property_id')
            ->join('property_subcategories as s', 's.id', '=', 'p.property_subcategory_id')
            ->join('property_categories as c', 'c.id', '=', 's.property_category_id')
            ->selectRaw("'property' AS kind, {$key} AS key, {$label} AS label, {$detail} AS detail")
            ->groupByRaw("{$key}, {$label}, {$detail}"))
            ->get();

        // 大量借用品項不指定單件，任何層級都以品項池為單位列出
        $pools = $metrics(BorrowStats::lentItems($filters)
            ->join('property_pools as pp', 'pp.id', '=', 'i.property_pool_id')
            ->selectRaw("'pool' AS kind, pp.id AS key, pp.name AS label, '大量借用品項' AS detail")
            ->groupBy('pp.id', 'pp.name'))
            ->get();

        $rows = $serial->concat($pools)
            ->map(fn ($r) => [
                'kind' => $r->kind,
                'key' => (int) $r->key,
                'label' => $r->label,
                'detail' => $r->detail,
                'times' => (int) $r->times,
                'quantity' => (int) $r->quantity,
                'item_days' => (int) $r->item_days,
                // 單件器材可以點進反查頁
                'property_id' => $level === 'property' && $r->kind === 'property' ? (int) $r->key : null,
            ])
            ->sortBy([['times', 'desc'], ['quantity', 'desc'], ['label', 'asc']])
            ->take(self::LIMIT)
            ->values();

        return Inertia::render('Admin/Stats/Popular', [
            'filters' => [...$filters, 'level' => $level],
            'rows' => $rows,
        ]);
    }

    public function departments(Request $request): Response
    {
        $filters = $request->validate(BorrowStats::RANGE_RULES);
        $department = DepartmentAlias::CANONICAL_SQL;

        $rows = BorrowStats::withItemQuantity(BorrowStats::withDepartment(BorrowStats::lentRequests($filters)))
            ->leftJoin('borrow_request_overviews as o', 'o.id', '=', 'r.id')
            ->selectRaw("{$department} AS department")
            ->selectRaw('count(*) AS times')
            ->selectRaw('coalesce(sum(iq.quantity), 0) AS quantity')
            ->selectRaw('count(*) FILTER (WHERE o.status IN (?, ?)) AS late', [BorrowRequestStatus::Overdue->value, BorrowRequestStatus::ReturnedLate->value])
            ->selectRaw('max(r.borrow_date) AS last_borrow_date')
            ->selectRaw('json_agg(DISTINCT btrim(r.borrower_department)) AS spellings')
            ->groupByRaw($department)
            ->orderByDesc('times')
            ->orderBy('department')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn ($r) => [
                'department' => $r->department,
                'times' => (int) $r->times,
                'quantity' => (int) $r->quantity,
                'late' => (int) $r->late,
                'last_borrow_date' => substr((string) $r->last_borrow_date, 0, 10),
                'spellings' => json_decode((string) $r->spellings, true) ?? [],
            ]);

        return Inertia::render('Admin/Stats/Departments', [
            'filters' => $filters,
            'rows' => $rows,
        ]);
    }

    public function overdue(Request $request): Response
    {
        $filters = $request->validate([
            ...BorrowStats::RANGE_RULES,
            'by' => ['nullable', Rule::in(['department', 'person'])],
        ]);
        $by = $filters['by'] ?? 'department';
        $department = DepartmentAlias::CANONICAL_SQL;

        $query = BorrowStats::withDepartment(BorrowStats::inRange(DB::table('borrow_request_overviews as r'), $filters))
            ->whereIn('r.status', [BorrowRequestStatus::Overdue->value, BorrowRequestStatus::ReturnedLate->value])
            ->selectRaw('count(*) AS times')
            ->selectRaw('count(*) FILTER (WHERE r.status = ?) AS ongoing', [BorrowRequestStatus::Overdue->value])
            // 逾期天數：還的那天（未還算今天）減歸還日
            ->selectRaw('round(avg(coalesce(r.returned_at::date, current_date) - r.due_date), 1) AS avg_days')
            ->selectRaw('max(coalesce(r.returned_at::date, current_date) - r.due_date) AS max_days')
            ->selectRaw('max(r.due_date) AS last_due_date');

        if ($by === 'person') {
            $query->selectRaw('r.borrower_name AS name, r.phone')
                ->selectRaw("string_agg(DISTINCT {$department}, '、') AS department")
                ->groupBy('r.borrower_name', 'r.phone');
        } else {
            $query->selectRaw("{$department} AS name, NULL AS phone, NULL AS department")
                ->groupByRaw($department);
        }

        $rows = $query->orderByDesc('times')->orderByDesc('avg_days')->limit(self::LIMIT)->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'phone' => $r->phone,
                'department' => $r->department,
                'times' => (int) $r->times,
                'ongoing' => (int) $r->ongoing,
                'avg_days' => (float) $r->avg_days,
                'max_days' => (int) $r->max_days,
                'last_due_date' => substr((string) $r->last_due_date, 0, 10),
            ]);

        return Inertia::render('Admin/Stats/Overdue', [
            'filters' => [...$filters, 'by' => $by],
            'rows' => $rows,
        ]);
    }

    public function heatmap(Request $request): Response
    {
        $years = BorrowStats::lentRequests([])
            ->selectRaw('DISTINCT extract(year FROM r.borrow_date)::int AS year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($y) => (int) $y);
        $currentYear = (int) CarbonImmutable::today()->format('Y');

        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);
        $year = (int) ($filters['year'] ?? $years->first() ?? $currentYear);

        $range = ['from' => "{$year}-01-01", 'to' => "{$year}-12-31"];

        $days = BorrowStats::withItemQuantity(BorrowStats::lentRequests($range))
            ->selectRaw('r.borrow_date::date AS day')
            ->selectRaw('count(*) AS requests')
            ->selectRaw('coalesce(sum(iq.quantity), 0) AS items')
            ->groupByRaw('r.borrow_date::date')
            ->get()
            ->mapWithKeys(fn ($r) => [substr((string) $r->day, 0, 10) => ['requests' => (int) $r->requests, 'items' => (int) $r->items]]);

        return Inertia::render('Admin/Stats/Heatmap', [
            'year' => $year,
            'years' => $years->push($currentYear)->unique()->sortDesc()->values(),
            'days' => $days->isEmpty() ? new \stdClass : $days,
        ]);
    }
}
