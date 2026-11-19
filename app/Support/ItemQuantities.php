<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * 把表單送來的 [{id, quantity?}, ...] 轉成 id => quantity（未指定數量為 null）。
 */
final class ItemQuantities
{
    /**
     * @return Collection<int, int|null>
     */
    public static function parse(mixed $items): Collection
    {
        $result = [];

        foreach (is_array($items) ? $items : [] as $item) {
            if (is_array($item) && isset($item['id'])) {
                $result[(int) $item['id']] = isset($item['quantity']) ? (int) $item['quantity'] : null;
            }
        }

        return new Collection($result);
    }
}
