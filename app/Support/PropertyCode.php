<?php

namespace App\Support;

use App\Models\Location;
use App\Models\Property;
use App\Models\PropertySubcategory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 財產編號 8 碼：地點(1) + 主要類別(3) + 次要類別(1) + 流水號(3)，例如 J2000001。
 */
final class PropertyCode
{
    public const SERIAL_DIGITS = 3;

    public const MAX_SERIAL = 999;

    public static function prefix(Location $location, PropertySubcategory $subcategory): string
    {
        return $location->ssid_prefix.$subcategory->fullCode();
    }

    /**
     * 下一個可用的財產編號（目前最大流水號 + 1）。
     * 只是建議值；兩人同時新增時以 properties.ssid 的 unique constraint 為準。
     */
    public static function next(Location $location, PropertySubcategory $subcategory): string
    {
        $prefix = self::prefix($location, $subcategory);

        $maxSerial = (int) Property::query()
            ->where('ssid', 'like', $prefix.'%')
            ->max(DB::raw('substring(ssid from 6 for 3)::int'));

        if ($maxSerial >= self::MAX_SERIAL) {
            throw new RuntimeException("財產編號 {$prefix} 的流水號已用完（{$maxSerial}），請另起類別編號。");
        }

        return $prefix.str_pad((string) ($maxSerial + 1), self::SERIAL_DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{location: string, category: string, subcategory: string, serial: int}|null
     */
    public static function parse(string $ssid): ?array
    {
        if (! preg_match('/^([0-9A-Z])([0-9]{3})([0-9A-Z])([0-9]{3})$/', $ssid, $m)) {
            return null;
        }

        return ['location' => $m[1], 'category' => $m[2], 'subcategory' => $m[3], 'serial' => (int) $m[4]];
    }
}
