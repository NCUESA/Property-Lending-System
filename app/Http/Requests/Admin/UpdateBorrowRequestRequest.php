<?php

namespace App\Http\Requests\Admin;

use App\Models\BorrowRequest;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBorrowRequestRequest extends FormRequest
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
        $borrow = $this->route('borrow');
        $borrowDate = $borrow instanceof BorrowRequest ? $borrow->borrow_date->toDateString() : 'today';

        return [
            'remark' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'due_date' => ['sometimes', 'required', 'date', "after_or_equal:{$borrowDate}"],
        ];
    }

    public function attributes(): array
    {
        return ['remark' => '備註', 'due_date' => '歸還日期'];
    }
}
