<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PropertySubcategoryRequest;
use App\Models\PropertyCategory;
use App\Models\PropertySubcategory;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class PropertySubcategoryController extends Controller
{
    public function store(PropertySubcategoryRequest $request, PropertyCategory $category): RedirectResponse
    {
        $category->subcategories()->create($request->validated());

        Toast::success('已新增次要類別。');

        return back();
    }

    public function update(PropertySubcategoryRequest $request, PropertySubcategory $subcategory): RedirectResponse
    {
        if ($subcategory->code !== $request->input('code') && $subcategory->properties()->exists()) {
            throw ValidationException::withMessages(['code' => '此類別已有器材使用，不能修改編號（財產編號會對不上）。']);
        }

        $subcategory->update($request->validated());

        Toast::success('已更新次要類別。');

        return back();
    }

    public function destroy(PropertySubcategory $subcategory): RedirectResponse
    {
        if ($subcategory->properties()->exists()) {
            throw ValidationException::withMessages(['subcategory' => '此類別已有器材使用，不能刪除。']);
        }

        $subcategory->delete();

        Toast::success('已刪除次要類別。');

        return back();
    }
}
