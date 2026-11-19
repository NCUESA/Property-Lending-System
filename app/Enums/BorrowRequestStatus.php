<?php

namespace App\Enums;

/**
 * 整張借用單的狀態，由 Actions 依明細狀態推導後寫入，供列表篩選與分頁使用。
 */
enum BorrowRequestStatus: string
{
    case Pending = 'pending';               // 已送出，尚未出借
    case Lent = 'lent';                     // 已出借（含部分歸還）
    case Returned = 'returned';             // 全部歸還
    case Cancelled = 'cancelled';           // 整單退回

    public function label(): string
    {
        return match ($this) {
            self::Pending => '申請中',
            self::Lent => '借出中',
            self::Returned => '已歸還',
            self::Cancelled => '已退回',
        };
    }
}
