<?php

namespace App\Support\Legacy;

/**
 * 舊系統財產編號的修正表。器材匯入（property:import-legacy）與借用紀錄匯入
 * （borrow:import-legacy）必須用同一份，借用明細才對得到修正後的器材。
 */
final class LegacyPropertyCodes
{
    /**
     * 舊 property.id → 新編號。以 id 為鍵，因為舊資料有兩筆共用同一個編號。
     *
     * - 9：類別碼 400 不在 coderule 任何區間（最大到 299）。它和 41030001 是同型號的
     *   個人電腦主機，103-0 正是「個人電腦主機」；第 1 碼 3 對應實際存放的 307。
     * - 103：和 104（大型雙面展示板）重複使用 J0081004。它其實是海報架，屬於 008-0，
     *   而 J0080001–J0080005 已經被 L 型 POP 落地架用掉，所以接續流水號給 J0080006。
     */
    public const BY_ID = [
        '9' => '31030001',
        '103' => 'J0080006',
    ];

    /**
     * 借用明細只記編號、不記 id，所以另外列出「明細上的舊編號 → 新編號」。
     * J0081004 不在這裡：明細上的 J0081004 都視為保留原編號的 104（大型雙面展示板）。
     */
    public const BY_LEGACY_SSID = [
        '34000001' => '31030001',
    ];

    public static function forProperty(string $legacyId, string $legacySsid): string
    {
        return self::BY_ID[$legacyId] ?? $legacySsid;
    }

    public static function forBorrowItem(string $legacySsid): string
    {
        return self::BY_LEGACY_SSID[$legacySsid] ?? $legacySsid;
    }
}
