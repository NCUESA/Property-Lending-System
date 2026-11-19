<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
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
            'deposit_amount' => ['required', 'integer', 'min:0', 'max:100000'],
            'max_days' => ['required', 'integer', 'min:1', 'max:365'],
            'default_open' => ['required', 'boolean'],
            'allow_advance' => ['required', 'boolean'],
            'notices' => ['array', 'max:30'],
            'notices.*' => ['required', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'deposit_amount' => '押金金額',
            'max_days' => '最長借用天數',
            'default_open' => '預設開放借用',
            'allow_advance' => '開放預借',
            'notices' => '注意事項',
            'notices.*' => '注意事項',
        ];
    }
}
