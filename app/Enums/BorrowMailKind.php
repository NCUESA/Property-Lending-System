<?php

namespace App\Enums;

/**
 * 三種借用通知信。每一種在後台都有自己的開關與模板。
 */
enum BorrowMailKind: string
{
    case Submitted = 'submitted';   // 申請送出
    case Lent = 'lent';             // 器材已出借
    case DueReminder = 'due';       // 歸還到期提醒

    public function label(): string
    {
        return match ($this) {
            self::Submitted => '申請確認信',
            self::Lent => '出借通知信',
            self::DueReminder => '歸還提醒信',
        };
    }

    /** settings 的 key 前綴，例如 notify.submitted.subject */
    public function key(string $field): string
    {
        return "notify.{$this->value}.{$field}";
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::Submitted => '【{{ location }}】借用申請已收到',
            self::Lent => '【{{ location }}】器材已借出，請於 {{ due_date }} 前歸還',
            self::DueReminder => '【{{ location }}】提醒：器材將於 {{ due_date }} 到期',
        };
    }

    public function defaultBody(): string
    {
        return match ($this) {
            self::Submitted => <<<'TXT'
                {{ borrower_name }} 您好：

                您的借用申請已經送出，單號 {{ request_id }}。

                取用地點：{{ location }}
                借用日期：{{ borrow_date }}
                歸還日期：{{ due_date }}

                借用品項：
                {{ item_list }}

                請於借用日當天攜帶證件與押金至取用地點辦理取件。
                TXT,
            self::Lent => <<<'TXT'
                {{ borrower_name }} 您好：

                您借用的器材已經交付，單號 {{ request_id }}。

                借用品項：
                {{ item_list }}

                請於 {{ due_date }} 前歸還，逾期將不退還押金。
                TXT,
            self::DueReminder => <<<'TXT'
                {{ borrower_name }} 您好：

                提醒您，以下借用的器材將於 {{ due_date }} 到期（還有 {{ days_left }} 天）。

                借用品項：
                {{ item_list }}

                請記得準時至 {{ location }} 歸還，逾期將不退還押金。
                TXT,
        };
    }
}
