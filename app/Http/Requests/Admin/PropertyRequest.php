<?php

namespace App\Http\Requests\Admin;

use App\Models\Property;
use App\Support\AuthLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyRequest extends FormRequest
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
        /** @var Property|null $property */
        $property = $this->route('property');

        return [
            'ssid' => ['required', 'string', 'size:8', 'regex:/^[0-9A-Z][0-9]{3}[0-9A-Z][0-9]{3}$/', Rule::unique('properties', 'ssid')->ignore($property)],
            'property_subcategory_id' => ['required', 'integer', 'exists:property_subcategories,id'],
            'sequence_no' => ['nullable', 'integer', 'min:1', 'max:999'],
            'name' => ['required', 'string', 'max:100'],
            'second_name' => ['nullable', 'string', 'max:100'],
            'spec' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'integer', 'min:0'],
            'department' => ['nullable', 'string', 'max:50'],
            'custodian' => ['nullable', 'string', 'max:50'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'property_pool_id' => ['nullable', 'integer', 'exists:property_pools,id'],
            'acquired_on' => ['nullable', 'date'],
            'is_school_owned' => ['required', 'boolean'],
            'auth_level' => ['required', 'integer', 'min:0', 'max:'.AuthLevel::MAX],
            'is_lendable' => ['required', 'boolean'],
            'remark' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'ssid' => '財產編號',
            'property_subcategory_id' => '類別',
            'sequence_no' => '財產序',
            'name' => '財產名稱',
            'second_name' => '第二名稱',
            'spec' => '規格',
            'price' => '金額',
            'department' => '使用單位',
            'custodian' => '保管人',
            'location_id' => '置放地點',
            'property_pool_id' => '品項池',
            'acquired_on' => '取得日期',
            'auth_level' => '借用權限等級',
            'image' => '財產照片',
        ];
    }

    public function messages(): array
    {
        return ['ssid.regex' => '財產編號格式為：地點 1 碼 + 主類 3 碼 + 次類 1 碼 + 流水號 3 碼，例如 J2000001。'];
    }
}
