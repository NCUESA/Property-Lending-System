<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 系統設定的驗證規則，依分頁（route 的 section 參數）切換。
 * 新增一頁設定時在這兩個 match 各加一組分支即可。
 */
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
        return match ($this->route('section')) {
            'borrow' => [
                'deposit_amount' => ['required', 'integer', 'min:0', 'max:100000'],
                'max_days' => ['required', 'integer', 'min:1', 'max:365'],
                'default_open' => ['required', 'boolean'],
                'allow_advance' => ['required', 'boolean'],
            ],
            'notices' => [
                'notices' => ['array', 'max:30'],
                'notices.*' => ['required', 'string', 'max:500'],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return match ($this->route('section')) {
            'borrow' => [
                'deposit_amount' => '押金金額',
                'max_days' => '最長借用天數',
                'default_open' => '預設開放借用',
                'allow_advance' => '開放預借',
            ],
            'notices' => [
                'notices' => '注意事項',
                'notices.*' => '注意事項',
            ],
            default => [],
        };
    }
}
