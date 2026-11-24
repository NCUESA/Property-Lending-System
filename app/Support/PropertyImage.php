<?php

namespace App\Support;

/**
 * 器材照片的網址。照片都在 public 磁碟的 properties/ 底下：
 * - 後台上傳的 image_path 是 properties/<uuid>.jpg
 * - 舊系統匯入的 image_path 只有檔名（40.jpg），組網址時補上資料夾
 */
final class PropertyImage
{
    public const DIRECTORY = 'properties';

    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (! str_contains($path, '/')) {
            $path = self::DIRECTORY.'/'.$path;
        }

        return asset('storage/'.$path);
    }
}
