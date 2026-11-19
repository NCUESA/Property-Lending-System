<?php

namespace App\Enums;

enum Role: string
{
    case Borrower = 'borrower';
    case Staff = 'staff';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Borrower => '借用人',
            self::Staff => '值班人員',
            self::Admin => '管理員',
        };
    }

    /** 管理員清冊：有後台權限的角色。 */
    public function isStaffOrAbove(): bool
    {
        return $this !== self::Borrower;
    }
}
