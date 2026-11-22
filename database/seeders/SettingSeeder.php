<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * 只在設定不存在時寫入預設值，避免覆蓋 admin 在後台改過的內容。
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'borrow.max_days' => 7,
            'borrow.deposit_amount' => 100,
            'borrow.default_open' => true,
            'borrow.allow_advance' => false,
            'borrow.notices' => [
                '根據器材借用法規借用時間最長一個禮拜（借用起始日自借用當日開始計算）。目前不開放預借。',
                '寒暑假借用時間請留意學生會公告。',
                '借用器材時需押證件乙張與押金 NTD 100 元（證件種類：學生證、健保卡、駕照、身分證等）。',
                '逾期歸還將不退還押金（可歸責於本會狀況除外）。',
                '器材借用如遺失或判定為人損，借用人應負賠償責任。',
                '確認器材歸還完畢後將退回押金與證件。',
            ],
            // 寄信預設關閉：SMTP 沒設定好就寄不出去，寧可讓管理員自己開
            'mail.enabled' => false,
            'notify.submitted.enabled' => false,
            'notify.lent.enabled' => false,
            'notify.due.enabled' => false,
            'notify.due.days_before' => 2,
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
