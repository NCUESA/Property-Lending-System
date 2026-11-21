<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\Setting;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 系統設定依功能分頁。
 *
 * 要新增一頁設定只要三步，不必動路由：
 *   1. 在 SECTIONS 加一組（slug、標題、Vue 元件、表單欄位 → settings key 的對應）
 *   2. 在 SettingsRequest::rules() 加對應的 match 分支
 *   3. 建立對應的 Vue 頁面
 */
class SettingController extends Controller
{
    /**
     * @var array<string, array{title: string, description: string, component: string, keys: array<string, string>}>
     */
    public const SECTIONS = [
        'borrow' => [
            'title' => '借用規則',
            'description' => '押金、借用天數與預借',
            'component' => 'Admin/Settings/Borrow',
            'keys' => [
                'deposit_amount' => 'borrow.deposit_amount',
                'max_days' => 'borrow.max_days',
                'default_open' => 'borrow.default_open',
                'allow_advance' => 'borrow.allow_advance',
            ],
        ],
        'notices' => [
            'title' => '注意事項',
            'description' => '借用申請頁的同意條款',
            'component' => 'Admin/Settings/Notices',
            'keys' => [
                'notices' => 'borrow.notices',
            ],
        ],
    ];

    /** 路由限制與預設導向都用這份清單，避免 slug 寫死在兩個地方。 */
    public const DEFAULT_SECTION = 'borrow';

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::SECTIONS);
    }

    public function edit(string $section): Response
    {
        $config = self::SECTIONS[$section];

        return Inertia::render($config['component'], [
            'settings' => collect($config['keys'])->map(fn (string $key) => Setting::get($key)),
        ]);
    }

    public function update(SettingsRequest $request, string $section): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($section, $data) {
            foreach (self::SECTIONS[$section]['keys'] as $field => $key) {
                Setting::put($key, $data[$field] ?? []);
            }
        });

        Toast::success('已更新設定。');

        return back();
    }

    /**
     * 側邊欄「系統」群組的項目。共享給所有頁面（見 HandleInertiaRequests），
     * 所以在 SECTIONS 新增一頁，側邊欄就會自己長出對應的連結。
     *
     * @return list<array{slug: string, title: string, description: string}>
     */
    public static function sectionsForNav(): array
    {
        return array_map(fn (string $slug) => [
            'slug' => $slug,
            'title' => self::SECTIONS[$slug]['title'],
            'description' => self::SECTIONS[$slug]['description'],
        ], self::slugs());
    }
}
