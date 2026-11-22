<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Borrow\SendDueReminders;
use App\Enums\BorrowMailKind;
use App\Enums\BorrowOpenMode;
use App\Enums\BorrowViolationKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Mail\BorrowNotificationMail;
use App\Models\Setting;
use App\Support\MailSettings;
use App\Support\MailTemplate;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

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
                'open_mode' => 'borrow.open_mode',
                'allow_advance' => 'borrow.allow_advance',
                'deposit_box_count' => 'borrow.deposit_box_count',
                'fixed_duration' => 'borrow.fixed_duration',
            ],
        ],
        'penalty' => [
            'title' => '違規與封鎖',
            'description' => '各類違規的次數上限與封鎖天數',
            'component' => 'Admin/Settings/Penalty',
            // 每一種違規各有 limit 與 block_days，見 penaltyKeys()
            'keys' => [],
        ],
        'notices' => [
            'title' => '注意事項',
            'description' => '借用申請頁的同意條款',
            'component' => 'Admin/Settings/Notices',
            'keys' => [
                'notices' => 'borrow.notices',
            ],
        ],
        'mail' => [
            'title' => '寄信設定',
            'description' => 'SMTP 連線與寄件者',
            'component' => 'Admin/Settings/Mail',
            'keys' => MailSettings::KEYS,
        ],
        'notifications' => [
            'title' => '通知信',
            'description' => '信件模板與提醒時間',
            'component' => 'Admin/Settings/Notifications',
            'keys' => [
                'submitted_enabled' => 'notify.submitted.enabled',
                'submitted_subject' => 'notify.submitted.subject',
                'submitted_body' => 'notify.submitted.body',
                'lent_enabled' => 'notify.lent.enabled',
                'lent_subject' => 'notify.lent.subject',
                'lent_body' => 'notify.lent.body',
                'due_enabled' => 'notify.due.enabled',
                'due_subject' => 'notify.due.subject',
                'due_body' => 'notify.due.body',
                'due_days_before' => 'notify.due.days_before',
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

    /**
     * 「違規與封鎖」的欄位跟著 BorrowViolationKind 走，新增一種違規不必改這裡。
     *
     * @return array<string, string>
     */
    public static function penaltyKeys(): array
    {
        $keys = [];

        foreach (BorrowViolationKind::cases() as $kind) {
            $keys[$kind->value.'_limit'] = $kind->settingKey('limit');
            $keys[$kind->value.'_block_days'] = $kind->settingKey('block_days');
        }

        return $keys;
    }

    /** @return array<string, string> */
    private static function keysOf(string $section): array
    {
        return $section === 'penalty' ? self::penaltyKeys() : self::SECTIONS[$section]['keys'];
    }

    public function edit(string $section): Response
    {
        $config = self::SECTIONS[$section];
        $values = collect(self::keysOf($section))->map(fn (string $key) => Setting::get($key))->all();

        return Inertia::render($config['component'], [
            'settings' => $this->present($section, $values),
            ...$this->extraProps($section),
        ]);
    }

    public function update(SettingsRequest $request, string $section): RedirectResponse
    {
        $data = $this->prepare($section, $request->validated());

        DB::transaction(function () use ($section, $data) {
            foreach (self::keysOf($section) as $field => $key) {
                // prepare() 可能刻意拿掉某個欄位（例如密碼留空代表不變更），這種就跳過不寫
                if (array_key_exists($field, $data)) {
                    Setting::put($key, $data[$field]);
                }
            }
        });

        Toast::success('已更新設定。');

        return back();
    }

    /**
     * 寄一封測試信，當場確認 SMTP 設定是否可用。
     * 失敗時把 SMTP 的錯誤訊息原樣帶回表單，否則管理員只會看到「寄不出去」卻不知道為什麼。
     */
    public function sendTestMail(Request $request): RedirectResponse
    {
        $to = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ])['email'];

        MailSettings::apply();
        Mail::purge('smtp');

        try {
            Mail::to($to)->send(new BorrowNotificationMail(
                '【'.config('app.name').'】測試信',
                "這是一封測試信。\n\n如果你收到它，表示後台的 SMTP 設定可以正常寄信。",
            ));
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'email' => '寄送失敗：'.$e->getMessage(),
            ]);
        }

        Toast::success("測試信已寄至 {$to}。");

        return back();
    }

    /**
     * 回傳給表單前的加工。
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function present(string $section, array $values): array
    {
        if ($section === 'mail') {
            // SMTP 密碼不回傳給前端，只告訴它「有沒有設定過」
            $values['password'] = null;

            return $values;
        }

        if ($section === 'borrow') {
            $values['open_mode'] ??= BorrowOpenMode::AlwaysOpen->value;

            return $values;
        }

        if ($section === 'penalty') {
            foreach (BorrowViolationKind::cases() as $kind) {
                $values[$kind->value.'_limit'] ??= $kind->defaultLimit();
                $values[$kind->value.'_block_days'] ??= $kind->defaultBlockDays();
            }

            return $values;
        }

        if ($section === 'notifications') {
            foreach (BorrowMailKind::cases() as $kind) {
                $values[$kind->value.'_subject'] ??= $kind->defaultSubject();
                $values[$kind->value.'_body'] ??= $kind->defaultBody();
            }

            $values['due_days_before'] ??= SendDueReminders::DEFAULT_DAYS_BEFORE;
        }

        return $values;
    }

    /**
     * 寫入前的加工。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(string $section, array $data): array
    {
        if ($section === 'mail') {
            $password = (string) ($data['password'] ?? '');

            // 留空代表沿用原本的密碼，所以直接把欄位拿掉不寫入
            if ($password === '') {
                unset($data['password']);
            } else {
                $data['password'] = MailSettings::encryptPassword($password);
            }
        }

        return $data;
    }

    /**
     * 分頁額外需要的資料。
     *
     * @return array<string, mixed>
     */
    private function extraProps(string $section): array
    {
        return match ($section) {
            'borrow' => ['openModes' => BorrowOpenMode::options()],
            'mail' => ['passwordSet' => MailSettings::password() !== null],
            'penalty' => ['kinds' => array_map(fn (BorrowViolationKind $k) => [
                'value' => $k->value,
                'label' => $k->label(),
                'description' => $k->description(),
            ], BorrowViolationKind::cases())],
            'notifications' => ['variables' => MailTemplate::VARIABLES],
            default => [],
        };
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
