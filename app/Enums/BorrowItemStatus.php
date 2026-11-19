<?php

namespace App\Enums;

enum BorrowItemStatus: string
{
    case Pending = 'pending';     // 舊 2：申請中
    case Lent = 'lent';           // 舊 1：已借出
    case Cancelled = 'cancelled'; // 舊 0：未借出、退回
    case Returned = 'returned';   // 舊 3：已歸還

    /** 佔用器材中的狀態，對應 partial unique index 的條件。 */
    public const ACTIVE = [self::Pending, self::Lent];

    public function isActive(): bool
    {
        return in_array($this, self::ACTIVE, true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => '申請中',
            self::Lent => '已借出',
            self::Cancelled => '已退回',
            self::Returned => '已歸還',
        };
    }

    public static function fromLegacy(int $value): self
    {
        return match ($value) {
            2 => self::Pending,
            1 => self::Lent,
            0 => self::Cancelled,
            3 => self::Returned,
            default => throw new \ValueError("Unknown legacy borrow item status: {$value}"),
        };
    }
}
