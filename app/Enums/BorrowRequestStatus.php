<?php

namespace App\Enums;

/**
 * 借用單的顯示狀態，由 VIEW borrow_request_overviews 依明細與日期即時計算，不存在資料表裡。
 */
enum BorrowRequestStatus: string
{
    case Pending = 'pending';                       // 已送出，等待取件
    case Expired = 'expired';                       // 借用日已過仍未取件
    case Lent = 'lent';                             // 借出中
    case Extended = 'extended';                     // 展延過、仍借出中且未逾期
    case PartiallyReturned = 'partially_returned';  // 部分歸還
    case Overdue = 'overdue';                       // 超過歸還日仍有器材未還
    case ReturnedLate = 'returned_late';            // 全部歸還，但超過歸還日才還
    case Returned = 'returned';                     // 全部歸還
    case Cancelled = 'cancelled';                   // 整單退回

    public function label(): string
    {
        return match ($this) {
            self::Pending => '申請中',
            self::Expired => '逾期未取',
            self::Lent => '借出中',
            self::Extended => '展延中',
            self::PartiallyReturned => '部分歸還',
            self::Overdue => '逾期未還',
            self::ReturnedLate => '逾期歸還',
            self::Returned => '已歸還',
            self::Cancelled => '已退回',
        };
    }
}
