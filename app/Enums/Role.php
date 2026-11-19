<?php

namespace App\Enums;

enum Role: string
{
    case Staff = 'staff';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Staff => '值班人員',
            self::Admin => '管理員',
        };
    }
}
