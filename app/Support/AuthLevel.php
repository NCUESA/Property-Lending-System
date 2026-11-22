<?php

namespace App\Support;

/**
 * 借用權限等級。
 *
 * 器材／品項的 auth_level 是「借它需要幾級」，0 代表公開、訪客也能借。
 * 群組的 auth_level 是「這個群組有幾級」。
 *
 * 可借的條件有兩個，缺一不可：
 *   1. 群組的授權範圍涵蓋這件器材（單一器材／主類別／次類別／品項）
 *   2. 群組等級 >= 器材等級
 *
 * 範圍決定「碰得到哪些東西」，等級決定「碰得到多敏感的東西」。
 */
final class AuthLevel
{
    public const MAX = 5;

    public static function label(int $level): string
    {
        return $level === 0 ? '公開（訪客可借）' : "等級 {$level}";
    }

    /** 器材用：0 ~ MAX。 */
    public static function shortLabel(int $level): string
    {
        return $level === 0 ? '公開' : "等級 {$level}";
    }

    /** @return list<array{value: int, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (int $n) => ['value' => $n, 'label' => self::label($n)],
            range(0, self::MAX),
        );
    }

    /**
     * 群組用：0 代表這個群組借不到任何需要授權的器材。
     *
     * @return list<array{value: int, label: string}>
     */
    public static function groupOptions(): array
    {
        return array_map(
            fn (int $n) => [
                'value' => $n,
                'label' => $n === 0 ? '0 級（借不到需授權的器材）' : "等級 {$n}",
            ],
            range(0, self::MAX),
        );
    }
}
