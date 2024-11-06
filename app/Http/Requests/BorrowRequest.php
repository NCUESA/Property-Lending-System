<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BorrowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'understand' => 'required|in:y,n', // 必須為 'y' 或 'n'
            'borrow_place' => 'required|string',
            'borrow_department' => 'required|string',
            'borrow_person_name' => 'required|string',
            'phone' => 'required|digits:10', // 十位數的電話
            'email' => 'required|email',
            'borrow_date' => 'required|date|after_or_equal:today', // 借用日期不能小於當前日期
            'returned_date' => 'required|date|after_or_equal:borrow_date', // 歸還日期不能小於借用日期
            'borrow_items' => 'required|array|min:1', // 至少選擇一個借用項目
            'borrow_items.*' => 'required|string', // 借用項目需為字串
        ];
    }

    public function messages()
    {
        return [
            'understand.required' => '請填寫了解情況',
            'understand.in' => '了解情況只能是 "y" 或 "n"',
            'borrow_place.required' => '請填寫借用地點',
            'borrow_department.required' => '請填寫部門',
            'borrow_person_name.required' => '請填寫借用人姓名',
            'phone.required' => '請填寫電話號碼',
            'phone.digits' => '電話號碼應為10位數字',
            'email.required' => '請填寫電子郵件',
            'email.email' => '請填寫有效的電子郵件地址',
            'borrow_date.required' => '請填寫借用日期',
            'borrow_date.after_or_equal' => '借用日期不能小於當前日期',
            'returned_date.required' => '請填寫歸還日期',
            'returned_date.after_or_equal' => '歸還日期不能小於借用日期',
            'borrow_items.required' => '請選擇至少一項借用項目',
            'borrow_items.array' => '借用項目應為陣列格式',
            'borrow_items.*.required' => '每項借用項目必須填寫',
        ];
    }
}
