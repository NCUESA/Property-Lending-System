<?php

namespace App\Http\Requests\Admin;

use App\Models\PropertyCategory;
use App\Models\PropertySubcategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertySubcategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 路由已套用 can:admin
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $subcategory = $this->route('subcategory');
        $category = $this->route('category');
        $categoryId = $subcategory instanceof PropertySubcategory
            ? $subcategory->property_category_id
            : ($category instanceof PropertyCategory ? $category->id : null);

        return [
            'code' => [
                'required', 'string', 'regex:/^[0-9A-Z]$/',
                Rule::unique('property_subcategories', 'code')
                    ->where('property_category_id', $categoryId)
                    ->ignore($subcategory instanceof PropertySubcategory ? $subcategory : null),
            ],
            'name' => ['required', 'string', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return ['code' => '次要類別編號', 'name' => '類別名稱'];
    }

    public function messages(): array
    {
        return ['code.regex' => '次要類別編號必須是 0-9 或 A-Z 的單一字元。'];
    }
}
