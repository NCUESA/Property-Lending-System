<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PropertyCategoryRequest;
use App\Models\PropertyCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 編碼規則清冊：主要類別（次要類別見 PropertySubcategoryController）。
 */
class PropertyCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Categories/Index', [
            'categories' => PropertyCategory::query()
                ->with(['subcategories' => fn ($q) => $q->withCount('properties')->orderBy('code')])
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(PropertyCategoryRequest $request): RedirectResponse
    {
        PropertyCategory::create($request->validated());

        return back()->with('success', '已新增主要類別。');
    }

    public function update(PropertyCategoryRequest $request, PropertyCategory $category): RedirectResponse
    {
        if ($category->code !== $request->input('code') && $category->subcategories()->whereHas('properties')->exists()) {
            throw ValidationException::withMessages(['code' => '此類別已有器材使用，不能修改編號（財產編號會對不上）。']);
        }

        $category->update($request->validated());

        return back()->with('success', '已更新主要類別。');
    }

    public function destroy(PropertyCategory $category): RedirectResponse
    {
        if ($category->subcategories()->exists()) {
            throw ValidationException::withMessages(['category' => '請先刪除底下的次要類別。']);
        }

        $category->delete();

        return back()->with('success', '已刪除主要類別。');
    }
}
