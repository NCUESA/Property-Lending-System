<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    /** 表單欄位 → settings key */
    private const KEYS = [
        'deposit_amount' => 'borrow.deposit_amount',
        'max_days' => 'borrow.max_days',
        'default_open' => 'borrow.default_open',
        'allow_advance' => 'borrow.allow_advance',
        'notices' => 'borrow.notices',
    ];

    public function edit(): Response
    {
        return Inertia::render('Admin/Settings/Edit', [
            'settings' => collect(self::KEYS)->map(fn (string $key) => Setting::get($key)),
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            foreach (self::KEYS as $field => $key) {
                Setting::put($key, $data[$field] ?? []);
            }
        });

        return back()->with('success', '已更新設定。');
    }
}
