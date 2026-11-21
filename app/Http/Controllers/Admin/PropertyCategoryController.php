<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PropertyCategoryRequest;
use App\Models\PropertyCategory;
use App\Support\PropertyCode;
use App\Support\Toast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 編碼規則清冊：主要類別（次要類別見 PropertySubcategoryController）。
 */
class PropertyCategoryController extends Controller
{
    /** 每頁筆數的可選值（以主要類別計），第一個是預設值。 */
    public const PER_PAGE_OPTIONS = [10, 30, 50, 100];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:50'],
            'group' => ['nullable', 'string', Rule::in(array_column(PropertyCode::GROUPS, 'key'))],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ]);

        $group = PropertyCode::group($filters['group'] ?? null);

        $categories = PropertyCategory::query()
            // 搜尋同時比對主類別的編號與名稱，以及底下次要類別的名稱，
            // 否則搜「麥克風」會找不到它所屬的那個主類別。
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w
                ->where('code', 'ilike', "%{$term}%")
                ->orWhere('name', 'ilike', "%{$term}%")
                ->orWhereHas('subcategories', fn (Builder $s) => $s->where('name', 'ilike', "%{$term}%"))))
            ->when($group, fn (Builder $q) => $q->whereRaw('code::int between ? and ?', [$group['from'], $group['to']]))
            ->with(['subcategories' => fn ($q) => $q->withCount('properties')->orderBy('code')])
            ->orderBy('code')
            ->paginate($filters['per_page'] ?? self::PER_PAGE_OPTIONS[0])
            ->withQueryString();

        return Inertia::render('Admin/Categories/Index', [
            'filters' => $filters,
            'categories' => $categories,
            'groups' => $this->groupsWithCounts(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * 大類別按鈕要顯示各區間的類別數，數量是 0 的區間也保留，方便看出編碼規則的全貌。
     *
     * @return list<array{key: string, name: string, count: int}>
     */
    private function groupsWithCounts(): array
    {
        $counts = PropertyCategory::query()
            ->selectRaw('code::int as code_int')
            ->pluck('code_int');

        return array_map(fn (array $group) => [
            'key' => $group['key'],
            'name' => $group['name'],
            'count' => $counts->filter(fn (int $code) => $code >= $group['from'] && $code <= $group['to'])->count(),
        ], PropertyCode::GROUPS);
    }

    public function store(PropertyCategoryRequest $request): RedirectResponse
    {
        PropertyCategory::create($request->validated());

        Toast::success('已新增主要類別。');

        return back();
    }

    public function update(PropertyCategoryRequest $request, PropertyCategory $category): RedirectResponse
    {
        if ($category->code !== $request->input('code') && $category->subcategories()->whereHas('properties')->exists()) {
            throw ValidationException::withMessages(['code' => '此類別已有器材使用，不能修改編號（財產編號會對不上）。']);
        }

        $category->update($request->validated());

        Toast::success('已更新主要類別。');

        return back();
    }

    public function destroy(PropertyCategory $category): RedirectResponse
    {
        if ($category->subcategories()->exists()) {
            throw ValidationException::withMessages(['category' => '請先刪除底下的次要類別。']);
        }

        $category->delete();

        Toast::success('已刪除主要類別。');

        return back();
    }
}
