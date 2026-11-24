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
    public function index(): Response
    {
        // 列出借用單上出現過的每一種寫法，以及它目前合併到哪個名稱
        $rows = DB::table('borrow_requests as r')
            ->leftJoin('department_aliases as da', 'da.alias', '=', DB::raw('btrim(r.borrower_department)'))
            ->selectRaw('btrim(r.borrower_department) AS spelling, da.canonical, count(*) AS times, max(r.borrow_date) AS last_borrow_date')
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
            'rows' => $rows,
        ]);
    }

    /** 把寫法移進某個群組（統一名稱）。只移動這幾種寫法，原本群組的其他成員不受影響。 */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'aliases' => ['required', 'array', 'min:1'],
            'aliases.*' => ['required', 'string', 'max:100', 'distinct'],
            'canonical' => ['required', 'string', 'max:100', 'regex:/[\p{L}\p{N}]/u'],
        ], [
            'canonical.regex' => '群組名稱至少要有一個文字或數字。',
        ], ['aliases' => '寫法', 'canonical' => '群組名稱']);

        $canonical = trim($data['canonical']);
        $aliases = array_values(array_unique(array_map('trim', $data['aliases'])));

        DB::transaction(function () use ($aliases, $canonical) {
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

        return back();
    }

    /** 群組改名：整個群組（含與舊名稱同名的那種寫法）一起改到新名稱；新名稱已存在就等於合併兩個群組。 */
    public function rename(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'max:100'],
            'to' => ['required', 'string', 'max:100', 'regex:/[\p{L}\p{N}]/u'],
        ], [
            'to.regex' => '群組名稱至少要有一個文字或數字。',
        ], ['to' => '群組名稱']);

        $from = trim($data['from']);
        $to = trim($data['to']);

        if ($from === $to) {
            return back();
        }

        DB::transaction(function () use ($from, $to) {
            DepartmentAlias::query()->where('canonical', $from)->update(['canonical' => $to]);

            // 和舊名稱同名的那種寫法原本不需要對應，改名後要補一筆才會留在群組裡
            $usedAsSpelling = DB::table('borrow_requests')->whereRaw('btrim(borrower_department) = ?', [$from])->exists();

            if ($usedAsSpelling && ! DepartmentAlias::query()->where('alias', $from)->exists()) {
                DepartmentAlias::query()->create(['alias' => $from, 'canonical' => $to]);
            }

            // 新名稱本身若是別的群組的成員，拉回這個群組，免得形成鏈
            DepartmentAlias::query()->where('alias', $to)->delete();
        });

        Toast::success("群組已改名為「{$to}」");

        return back();
    }

    /** 取消合併，回到借用單上的原文。 */
    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'aliases' => ['required', 'array', 'min:1'],
            'aliases.*' => ['required', 'string', 'max:100'],
        ]);

        DepartmentAlias::query()->whereIn('alias', array_map('trim', $data['aliases']))->delete();

        return back();
    }
}
