<?php

namespace App\Support;

use Illuminate\Database\QueryException;

/**
 * 判斷 PostgreSQL 約束錯誤的種類，讓 Actions 把它們轉成給使用者看的驗證錯誤。
 */
final class DatabaseError
{
    public const UNIQUE_VIOLATION = '23505';

    public const CHECK_VIOLATION = '23514';

    public const EXCLUSION_VIOLATION = '23P01';

    public const FOREIGN_KEY_VIOLATION = '23503';

    public static function is(QueryException $e, string $sqlState, ?string $mentions = null): bool
    {
        return $e->getCode() === $sqlState
            && ($mentions === null || str_contains($e->getMessage(), $mentions));
    }
}
