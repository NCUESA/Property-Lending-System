<?php

namespace App\Http\Requests\Admin;

use App\Models\BorrowRequest;
use Illuminate\Foundation\Http\FormRequest;

class ExtendBorrowRequestRequest extends FormRequest
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
        $currentDue = $borrow instanceof BorrowRequest ? $borrow->due_date->toDateString() : 'today';

        return [
            // 展延就是往後延；要改早請改借用單本身的資料
            'due_date' => ['required', 'date', "after:{$currentDue}"],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['due_date' => '新的歸還日', 'reason' => '展延理由'];
    }

    public function messages(): array
    {
        return ['due_date.after' => '新的歸還日必須晚於原本的歸還日。'];
    }
}
