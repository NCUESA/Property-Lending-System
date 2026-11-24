<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepartmentAlias;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 單位同義詞：把借用單上各種手填的寫法，合併成統計用的同一個名稱。
 * 借用單上的原文不會被改動。
 */
class DepartmentAliasController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:50'],
        ]);

        // 列出借用單上出現過的每一種寫法，以及它目前合併到哪個名稱
        $rows = DB::table('borrow_requests as r')
            ->leftJoin('department_aliases as da', 'da.alias', '=', DB::raw('btrim(r.borrower_department)'))
            ->selectRaw('btrim(r.borrower_department) AS spelling, da.canonical, count(*) AS times, max(r.borrow_date) AS last_borrow_date')
            ->when($filters['q'] ?? null, fn ($q, string $term) => $q->where(fn ($w) => $w
                ->where('r.borrower_department', 'ilike', "%{$term}%")
                ->orWhere('da.canonical', 'ilike', "%{$term}%")))
            ->groupByRaw('btrim(r.borrower_department), da.canonical')
            ->orderByRaw('coalesce(da.canonical, btrim(r.borrower_department))')
            ->orderByDesc('times')
            ->get()
            ->map(fn ($r) => [
                'spelling' => $r->spelling,
                'canonical' => $r->canonical,
                'times' => (int) $r->times,
                'last_borrow_date' => substr((string) $r->last_borrow_date, 0, 10),
            ]);

        return Inertia::render('Admin/Stats/Aliases', [
            'filters' => $filters,
            'rows' => $rows,
            // 合併時可以直接選既有的名稱
            'canonicals' => DepartmentAlias::query()->distinct()->orderBy('canonical')->pluck('canonical'),
        ]);
    }

    /** 把勾選的寫法合併到同一個名稱。 */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'aliases' => ['required', 'array', 'min:1'],
            'aliases.*' => ['required', 'string', 'max:100', 'distinct'],
            'canonical' => ['required', 'string', 'max:100'],
        ], attributes: ['aliases' => '寫法', 'canonical' => '統一名稱']);

        $canonical = trim($data['canonical']);
        $aliases = array_values(array_unique(array_map('trim', $data['aliases'])));

        DB::transaction(function () use ($aliases, $canonical) {
            // 原本合併到這些寫法的，跟著改到新名稱，不會留下指向舊名稱的對應
            DepartmentAlias::query()->whereIn('canonical', $aliases)->update(['canonical' => $canonical]);

            foreach ($aliases as $alias) {
                if ($alias === $canonical) {
                    // 寫法本身就是統一名稱，不需要對應
                    DepartmentAlias::query()->where('alias', $alias)->delete();

                    continue;
                }

                DepartmentAlias::query()->updateOrCreate(['alias' => $alias], ['canonical' => $canonical]);
            }

            // 統一名稱自己若曾被合併到別處，取消那筆，免得形成鏈
            DepartmentAlias::query()->where('alias', $canonical)->delete();
        });

        Toast::success('已將 '.count($aliases)." 種寫法合併為「{$canonical}」");

        return back();
    }

    /** 取消合併，回到借用單上的原文。 */
    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'aliases' => ['required', 'array', 'min:1'],
            'aliases.*' => ['required', 'string', 'max:100'],
        ]);

        $deleted = DepartmentAlias::query()->whereIn('alias', array_map('trim', $data['aliases']))->delete();

        Toast::success("已取消 {$deleted} 種寫法的合併");

        return back();
    }
}
