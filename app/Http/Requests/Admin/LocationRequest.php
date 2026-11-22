<?php

namespace App\Http\Requests\Admin;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
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
        $location = $this->route('location');
        $id = $location instanceof Location ? $location->id : null;

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('locations', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:50'],
            // 財產編號的第一碼，見 App\Support\PropertyCode
            'ssid_prefix' => [
                'required', 'string', 'size:1', 'regex:/^[A-Z0-9]$/',
                Rule::unique('locations', 'ssid_prefix')->ignore($id),
            ],
            'is_pickup_point' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => '代碼',
            'name' => '名稱',
            'ssid_prefix' => '財產編號字首',
            'is_pickup_point' => '取用地點',
            'is_active' => '啟用',
            'sort_order' => '排序',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ssid_prefix.regex' => '財產編號字首只能是一個大寫英文字母或數字。',
        ];
    }
}
