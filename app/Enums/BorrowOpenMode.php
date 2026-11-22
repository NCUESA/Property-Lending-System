<?php

namespace App\Enums;

/**
 * 決定「某一天能不能借」的判斷方式。
 */
enum BorrowOpenMode: string
{
    /** 全部時段開放，「借用期間」那一頁完全不生效。 */
    case AlwaysOpen = 'always_open';

    /** 只有落在開放的「借用期間」內才能借，期間外一律不開放。 */
    case Periods = 'periods';

    /** 依值勤班表開放。尚未實作，要接外部 API。 */
    case DutyHours = 'duty_hours';

    public function label(): string
    {
        return match ($this) {
            self::AlwaysOpen => '預設開放借用',
            self::Periods => '設定內時間開放借用',
            self::DutyHours => '值勤時間內開放',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AlwaysOpen => '任何日期都可以借',
            self::Periods => '只有在「借用期間」裡設為開放的日期才能借',
            self::DutyHours => '依值勤班表自動判斷（Comming Soon）',
        };
    }

    /** 還沒接 API 的模式不能被選取，驗證與前端都看這個。 */
    public function available(): bool
    {
        return $this !== self::DutyHours;
    }

    /** @return list<self> */
    public static function selectable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $m) => $m->available()));
    }

    /** @return list<string> */
    public static function selectableValues(): array
    {
        return array_map(fn (self $m) => $m->value, self::selectable());
    }

    /** @return list<array{value: string, label: string, description: string, available: bool}> */
    public static function options(): array
    {
        return array_map(fn (self $m) => [
            'value' => $m->value,
            'label' => $m->label(),
            'description' => $m->description(),
            'available' => $m->available(),
        ], self::cases());
    }
}
