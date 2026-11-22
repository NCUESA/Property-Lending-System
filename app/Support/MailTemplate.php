<?php

namespace App\Support;

use App\Models\BorrowRequest;

/**
 * 通知信的模板渲染。
 *
 * 刻意不使用 Blade 來渲染後台填寫的模板：那等於讓管理員寫的字串被當成程式碼執行。
 * 這裡只做固定變數名稱的字串替換，變數值在 Blade view 裡才轉義。
 */
final class MailTemplate
{
    /** 模板可用的變數與說明，後台表單會把這份清單列給管理員看。 */
    public const VARIABLES = [
        'borrower_name' => '借用人姓名',
        'borrower_department' => '借用人單位',
        'location' => '取用地點',
        'borrow_date' => '借用日期',
        'due_date' => '歸還日期',
        'days_left' => '距離歸還日還有幾天',
        'item_list' => '借用品項清單（每行一項）',
        'request_id' => '借用單編號',
    ];

    /** 測試信沒有借用單，只有這幾個變數可以用。 */
    public const TEST_VARIABLES = [
        'app_name' => '系統名稱',
        'sent_at' => '寄出時間',
        'to' => '收件信箱',
    ];

    /**
     * @return array<string, string>
     */
    public static function testVariables(string $to): array
    {
        return [
            'app_name' => (string) config('app.name'),
            'sent_at' => now()->format('Y-m-d H:i'),
            'to' => $to,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function variablesFor(BorrowRequest $request): array
    {
        $items = $request->items
            ->map(fn ($item) => $item->property
                ? "{$item->property->ssid} {$item->property->name}"
                : ($item->pool?->name.' × '.$item->quantity))
            ->filter()
            ->map(fn (string $line) => '- '.$line)
            ->implode("\n");

        return [
            'borrower_name' => (string) $request->borrower_name,
            'borrower_department' => (string) $request->borrower_department,
            'location' => (string) $request->location?->name,
            'borrow_date' => $request->borrow_date->format('Y-m-d'),
            'due_date' => $request->due_date->format('Y-m-d'),
            'days_left' => (string) max(0, today()->diffInDays($request->due_date, false)),
            'item_list' => $items,
            'request_id' => (string) $request->id,
        ];
    }

    /**
     * 把 {{ 變數 }} 換成實際內容。未知的變數原樣保留，讓管理員看得出自己打錯字，
     * 而不是靜悄悄變成空白。
     *
     * @param  array<string, string>  $variables
     */
    public static function render(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn (array $m) => array_key_exists($m[1], $variables) ? $variables[$m[1]] : $m[0],
            $template,
        ) ?? $template;
    }
}
