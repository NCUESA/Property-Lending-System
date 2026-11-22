<?php

use App\Actions\Borrow\SendDueReminders;
use App\Enums\BorrowItemStatus;
use App\Enums\BorrowMailKind;
use App\Jobs\SendBorrowNotification;
use App\Mail\BorrowNotificationMail;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;
use App\Models\Property;
use App\Models\Setting;
use App\Models\User;
use App\Support\MailSettings;
use App\Support\MailTemplate;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([LocationSeeder::class, SettingSeeder::class]);
    $this->admin = User::factory()->admin()->create();
});

/** 讓寄信與指定那封信都處於啟用狀態 */
function enableMail(BorrowMailKind $kind): void
{
    Setting::put('mail.enabled', true);
    Setting::put('mail.host', 'smtp.example.org');
    Setting::put('mail.from_address', 'noreply@example.org');
    Setting::put($kind->key('enabled'), true);
}

function makeLentRequest(string $dueDate): BorrowRequest
{
    $request = BorrowRequest::factory()->create([
        'email' => 'borrower@example.org',
        'due_date' => $dueDate,
    ]);
    BorrowRequestItem::factory()->create([
        'borrow_request_id' => $request->id,
        'property_id' => Property::factory()->create()->id,
        'status' => BorrowItemStatus::Lent,
    ]);

    return $request;
}

describe('通知信開關', function () {
    it('寄信總開關關閉時什麼都不寄', function () {
        Mail::fake();
        Setting::put('mail.enabled', false);
        Setting::put(BorrowMailKind::Submitted->key('enabled'), true);

        (new SendBorrowNotification(makeLentRequest('2027-01-10'), BorrowMailKind::Submitted))->handle();

        Mail::assertNothingSent();
    });

    it('單封信的開關關閉時不寄那一封', function () {
        Mail::fake();
        Setting::put('mail.enabled', true);
        Setting::put(BorrowMailKind::Submitted->key('enabled'), false);

        (new SendBorrowNotification(makeLentRequest('2027-01-10'), BorrowMailKind::Submitted))->handle();

        Mail::assertNothingSent();
    });

    it('兩個開關都開才會寄給借用單上的信箱', function () {
        Mail::fake();
        enableMail(BorrowMailKind::Submitted);

        (new SendBorrowNotification(makeLentRequest('2027-01-10'), BorrowMailKind::Submitted))->handle();

        Mail::assertSent(BorrowNotificationMail::class, fn ($mail) => $mail->hasTo('borrower@example.org'));
    });
});

describe('歸還提醒', function () {
    it('只挑剛好在提醒天數上、且還有器材在外面的單子', function () {
        Queue::fake();
        enableMail(BorrowMailKind::DueReminder);
        Setting::put(SendDueReminders::DAYS_BEFORE_KEY, 2);

        $hit = makeLentRequest(today()->addDays(2)->toDateString());
        $tooEarly = makeLentRequest(today()->addDays(5)->toDateString());

        // 已全數歸還的不該提醒
        $returned = BorrowRequest::factory()->create(['due_date' => today()->addDays(2)]);
        BorrowRequestItem::factory()->create([
            'borrow_request_id' => $returned->id,
            'property_id' => Property::factory()->create()->id,
            'status' => BorrowItemStatus::Returned,
        ]);

        expect((new SendDueReminders)->handle())->toBe(1);

        Queue::assertPushed(
            SendBorrowNotification::class,
            fn ($job) => $job->request->id === $hit->id && $job->kind === BorrowMailKind::DueReminder,
        );
        Queue::assertNotPushed(
            SendBorrowNotification::class,
            fn ($job) => in_array($job->request->id, [$tooEarly->id, $returned->id], true),
        );
    });

    it('同一張單只提醒一次', function () {
        Queue::fake();
        enableMail(BorrowMailKind::DueReminder);
        Setting::put(SendDueReminders::DAYS_BEFORE_KEY, 2);
        makeLentRequest(today()->addDays(2)->toDateString());

        expect((new SendDueReminders)->handle())->toBe(1)
            ->and((new SendDueReminders)->handle())->toBe(0);
    });

    it('提醒天數可以設定', function () {
        Queue::fake();
        enableMail(BorrowMailKind::DueReminder);
        Setting::put(SendDueReminders::DAYS_BEFORE_KEY, 5);
        makeLentRequest(today()->addDays(5)->toDateString());

        expect((new SendDueReminders)->handle())->toBe(1);
    });

    it('提醒信關閉時不跑', function () {
        Queue::fake();
        enableMail(BorrowMailKind::DueReminder);
        Setting::put(BorrowMailKind::DueReminder->key('enabled'), false);
        makeLentRequest(today()->addDays(2)->toDateString());

        expect((new SendDueReminders)->handle())->toBe(0);
    });
});

describe('模板', function () {
    it('替換變數，未知變數原樣保留', function () {
        $rendered = MailTemplate::render(
            '{{ borrower_name }} 於 {{ due_date }} 歸還，{{ nope }}',
            ['borrower_name' => '王小明', 'due_date' => '2027-01-10'],
        );

        expect($rendered)->toBe('王小明 於 2027-01-10 歸還，{{ nope }}');
    });

    it('模板內容不會被當成程式碼執行', function () {
        Mail::fake();
        enableMail(BorrowMailKind::Submitted);
        Setting::put(BorrowMailKind::Submitted->key('body'), '{{ 7 * 7 }} <script>alert(1)</script>');

        (new SendBorrowNotification(makeLentRequest('2027-01-10'), BorrowMailKind::Submitted))->handle();

        Mail::assertSent(BorrowNotificationMail::class, function ($mail) {
            $html = $mail->render();

            return ! str_contains($html, '49')
                && ! str_contains($html, '<script>alert(1)</script>');
        });
    });
});

describe('SMTP 設定', function () {
    it('密碼加密儲存，而且不會回傳到前端', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.section.update', 'mail'), [
                'enabled' => true,
                'host' => 'smtp.example.org',
                'port' => 587,
                'scheme' => 'smtp',
                'username' => 'bot',
                'password' => 's3cret',
                'from_address' => 'noreply@example.org',
                'from_name' => '學生會',
            ])->assertSessionHasNoErrors();

        expect(Setting::get('mail.password'))->not->toBe('s3cret')
            ->and(Crypt::decryptString(Setting::get('mail.password')))->toBe('s3cret')
            ->and(MailSettings::password())->toBe('s3cret');

        $this->actingAs($this->admin)
            ->get(route('admin.settings.section.edit', 'mail'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.password', null)
                ->where('passwordSet', true));
    });

    it('密碼留空代表不變更', function () {
        Setting::put('mail.password', MailSettings::encryptPassword('original'));

        $this->actingAs($this->admin)
            ->put(route('admin.settings.section.update', 'mail'), [
                'enabled' => true,
                'host' => 'smtp.example.org',
                'port' => 587,
                'scheme' => 'smtp',
                'username' => 'bot',
                'password' => '',
                'from_address' => 'noreply@example.org',
                'from_name' => '學生會',
            ])->assertSessionHasNoErrors();

        expect(MailSettings::password())->toBe('original');
    });

    it('主機或寄件者沒填完整就不覆蓋 .env 的設定', function () {
        config(['mail.mailers.smtp.host' => 'from-env']);
        Setting::put('mail.host', '');

        MailSettings::apply();

        expect(config('mail.mailers.smtp.host'))->toBe('from-env');
    });

    it('EHLO 網域不會送出 localhost', function () {
        // APP_URL 是 localhost 時，預設的 local_domain 會害 Gmail 以 421 (EHLO) 拒絕
        config(['mail.mailers.smtp.local_domain' => 'localhost']);
        Setting::put('mail.host', 'smtp.gmail.com');
        Setting::put('mail.from_address', 'noreply@ncuesa.org.tw');

        MailSettings::apply();

        expect(config('mail.mailers.smtp.local_domain'))->toBe('ncuesa.org.tw');
    });

    it('有指定 EHLO 網域時以設定為準', function () {
        Setting::put('mail.host', 'smtp.gmail.com');
        Setting::put('mail.from_address', 'noreply@ncuesa.org.tw');
        Setting::put('mail.ehlo_domain', 'mail.ncuesa.org.tw');

        MailSettings::apply();

        expect(config('mail.mailers.smtp.local_domain'))->toBe('mail.ncuesa.org.tw');
    });

    it('加密方式只接受 Symfony 支援的 scheme', function () {
        $payload = [
            'enabled' => true, 'host' => 'smtp.gmail.com', 'port' => 587,
            'username' => 'bot', 'password' => '', 'from_address' => 'a@example.org', 'from_name' => 'x',
        ];

        $this->actingAs($this->admin)
            ->put(route('admin.settings.section.update', 'mail'), [...$payload, 'scheme' => 'tls'])
            ->assertSessionHasErrors('scheme');

        $this->actingAs($this->admin)
            ->put(route('admin.settings.section.update', 'mail'), [...$payload, 'scheme' => 'smtps'])
            ->assertSessionHasNoErrors();
    });

    it('只有管理員能改寄信設定', function () {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.settings.section.edit', 'mail'))
            ->assertForbidden();

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('admin.settings.mail.test'), ['email' => 'x@example.org'])
            ->assertForbidden();
    });
});
