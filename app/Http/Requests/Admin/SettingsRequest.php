<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
                'notices' => ['present', 'array', 'max:30'],
                'notices.*' => ['required', 'string', 'max:500'],
            ],
            'mail' => [
                'enabled' => ['required', 'boolean'],
                'host' => ['nullable', 'string', 'max:255'],
                'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                // Symfony 的 EsmtpTransportFactory 只接受這兩種，留空則由 Laravel 依 port 判斷
                'scheme' => ['nullable', Rule::in(['smtp', 'smtps'])],
                'username' => ['nullable', 'string', 'max:255'],
                // 留空代表不變更；真的要清空密碼請把 SMTP 主機一起清掉
                'password' => ['nullable', 'string', 'max:255'],
                'from_address' => ['nullable', 'email', 'max:255'],
                'from_name' => ['nullable', 'string', 'max:100'],
                'ehlo_domain' => ['nullable', 'string', 'max:255'],
            ],
            'notifications' => [
                'submitted_enabled' => ['required', 'boolean'],
                'submitted_subject' => ['required', 'string', 'max:200'],
                'submitted_body' => ['required', 'string', 'max:5000'],
                'lent_enabled' => ['required', 'boolean'],
                'lent_subject' => ['required', 'string', 'max:200'],
                'lent_body' => ['required', 'string', 'max:5000'],
                'due_enabled' => ['required', 'boolean'],
                'due_subject' => ['required', 'string', 'max:200'],
                'due_body' => ['required', 'string', 'max:5000'],
                'due_days_before' => ['required', 'integer', 'min:0', 'max:30'],
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
            'mail' => [
                'enabled' => '啟用寄信',
                'host' => 'SMTP 主機',
                'port' => '連接埠',
                'scheme' => '加密方式',
                'username' => '帳號',
                'password' => '密碼',
                'from_address' => '寄件者信箱',
                'from_name' => '寄件者名稱',
                'ehlo_domain' => 'EHLO 網域',
            ],
            'notifications' => [
                'submitted_enabled' => '申請確認信',
                'submitted_subject' => '申請確認信主旨',
                'submitted_body' => '申請確認信內文',
                'lent_enabled' => '出借通知信',
                'lent_subject' => '出借通知信主旨',
                'lent_body' => '出借通知信內文',
                'due_enabled' => '歸還提醒信',
                'due_subject' => '歸還提醒信主旨',
                'due_body' => '歸還提醒信內文',
                'due_days_before' => '提前提醒天數',
            ],
            default => [],
        };
    }
}
