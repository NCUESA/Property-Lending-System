<?php

namespace App\Http\Requests\Admin;

use App\Support\AuthLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyPoolRequest extends FormRequest
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
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('property_pools', 'name')->where('location_id', $this->input('location_id'))->ignore($this->route('pool')),
            ],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_pickup_point', true)],
            'auth_level' => ['required', 'integer', 'min:0', 'max:'.AuthLevel::MAX],
            'remark' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => '品項名稱', 'location_id' => '取用地點', 'auth_level' => '借用權限等級', 'remark' => '備註'];
    }
}
