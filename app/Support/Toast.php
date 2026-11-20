<?php

namespace App\Support;

use Inertia\Inertia;

/**
 * 一次性的提示訊息（Inertia v3 flash），前端 flashToast.ts 會以 toast 顯示。
 */
final class Toast
{
    public static function success(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }

    public static function error(string $message): void
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);
    }
}
