<?php

namespace App\Http\Requests\Borrow;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBorrowRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 訪客也能申請；能借哪些器材由 SubmitBorrowRequest 依登入身分判斷
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $today = today()->toDateString();
        $maxDays = max(1, (int) Setting::get('borrow.max_days', 7));
        $allowAdvance = (bool) Setting::get('borrow.allow_advance', false);

        // 借用日當天算第 1 天，所以最晚歸還日 = 借用日 + (max_days − 1)
        $latestDue = $this->date('borrow_date')?->addDays($maxDays - 1)->toDateString();

        /*
         * 固定借用天數＝一律借滿 max_days，歸還日不給自選（送什麼都以算出來的為準）。
         * 關閉＝借用人自己挑歸還日，不設上限，超過規定由後台借用單示警。
         */
        $fixed = (bool) Setting::get('borrow.fixed_duration', true);

        return [
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_pickup_point', true)],
            'borrower_department' => ['required', 'string', 'max:100'],
            'borrower_name' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'digits:10'],
            'email' => ['required', 'email', 'max:255'],
            'borrow_date' => $allowAdvance
                ? ['required', 'date', "after_or_equal:{$today}"]
                : ['required', 'date', "date_equals:{$today}"],
            'due_date' => array_filter([
                'required', 'date', 'after_or_equal:borrow_date',
                $fixed && $latestDue ? "before_or_equal:{$latestDue}" : null,
            ]),
            'agreed_terms' => ['accepted'],
            'property_ids' => ['array', 'required_without:pools'],
            'property_ids.*' => ['integer', 'distinct'],
            'pools' => ['array', 'required_without:property_ids'],
            'pools.*.id' => ['required', 'integer', 'distinct'],
            'pools.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /** 固定借用天數時，歸還日一律改成「借用日 + max_days − 1」，不管使用者送什麼。 */
    protected function prepareForValidation(): void
    {
        if (! (bool) Setting::get('borrow.fixed_duration', true)) {
            return;
        }

        $maxDays = max(1, (int) Setting::get('borrow.max_days', 7));
        $borrowDate = $this->date('borrow_date');

        if ($borrowDate !== null) {
            $this->merge(['due_date' => $borrowDate->addDays($maxDays - 1)->toDateString()]);
        }
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (empty($this->input('property_ids')) && empty($this->input('pools'))) {
                    $validator->errors()->add('property_ids', '請至少選擇一項器材。');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'location_id' => '借用地點',
            'borrower_department' => '單位／系所',
            'borrower_name' => '聯絡人姓名',
            'phone' => '聯絡電話',
            'email' => '電子郵件',
            'borrow_date' => '借用日期',
            'due_date' => '歸還日期',
            'agreed_terms' => '已閱讀並同意注意事項',
            'property_ids' => '器材',
            'pools' => '品項',
            'pools.*.quantity' => '數量',
        ];
    }

    public function messages(): array
    {
        return [
            'borrow_date.date_equals' => '目前不開放預借，借用日期必須是今天。',
        ];
    }
}
