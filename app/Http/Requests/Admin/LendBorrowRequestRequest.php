<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LendBorrowRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 路由已套用 can:staff
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'id_card_taken' => ['required', 'boolean'],
            'deposit_taken' => ['required', 'boolean'],
            'deposit_amount' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'deposit_box_no' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'items' => '出借項目',
            'items.*.quantity' => '出借數量',
            'deposit_amount' => '押金金額',
            'deposit_box_no' => '押金箱號',
        ];
    }
}
