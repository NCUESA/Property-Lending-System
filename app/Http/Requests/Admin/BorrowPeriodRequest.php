<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BorrowPeriodRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:50'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'is_open' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => '名稱', 'starts_on' => '開始日期', 'ends_on' => '結束日期', 'is_open' => '開放借用', 'note' => '說明'];
    }
}
