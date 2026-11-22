<?php

namespace App\Http\Requests\Admin;

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
        // 歸還日改由展延端點處理（需要理由），這裡只管備註
        return [
            'remark' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['remark' => '備註'];
    }
}
