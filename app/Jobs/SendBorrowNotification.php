<?php

namespace App\Jobs;

use App\Enums\BorrowMailKind;
use App\Mail\BorrowNotificationMail;
use App\Models\BorrowRequest;
use App\Models\Setting;
use App\Support\MailSettings;
use App\Support\MailTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * 寄出一封借用通知信。
 *
 * 放進佇列的理由：SMTP 可能很慢或暫時連不上，不應該讓借用人按「送出申請」時卡住，
 * 寄信失敗也不該讓整筆申請失敗。
 */
class SendBorrowNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly BorrowRequest $request,
        public readonly BorrowMailKind $kind,
    ) {}

    public function handle(): void
    {
        if (! MailSettings::enabled() || ! (bool) Setting::get($this->kind->key('enabled'), false)) {
            return;
        }

        $to = trim((string) $this->request->email);

        if ($to === '') {
            return;
        }

        MailSettings::apply();

        // worker 是長駐的，MailManager 會快取已建立的 mailer；
        // 不清掉的話後台改完 SMTP 設定仍會沿用舊連線。
        Mail::purge('smtp');

        $this->request->loadMissing(['items.property', 'items.pool', 'location']);
        $variables = MailTemplate::variablesFor($this->request);

        Mail::to($to)->send(new BorrowNotificationMail(
            MailTemplate::render((string) Setting::get($this->kind->key('subject'), $this->kind->defaultSubject()), $variables),
            MailTemplate::render((string) Setting::get($this->kind->key('body'), $this->kind->defaultBody()), $variables),
        ));
    }
}
