<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReturnBorrowRequestRequest extends FormRequest
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
            'items' => ['array'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'id_card_returned' => ['boolean'],
            'deposit_returned' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'items' => '歸還項目',
            'items.*.quantity' => '歸還數量',
        ];
    }
}
