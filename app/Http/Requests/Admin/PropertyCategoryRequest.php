<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyCategoryRequest extends FormRequest
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
        return [
            'code' => ['required', 'string', 'regex:/^[0-9]{3}$/', Rule::unique('property_categories', 'code')->ignore($this->route('category'))],
            'name' => ['required', 'string', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return ['code' => '主要類別編號', 'name' => '類別名稱'];
    }

    public function messages(): array
    {
        return ['code.regex' => '主要類別編號必須是 3 位數字。'];
    }
}
