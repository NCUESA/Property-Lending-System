<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * 後台設定的 SMTP 連線資訊。
 *
 * 這些值存在 settings 表（密碼加密），寄信前才套用到 runtime config，
 * 避免每個 request 都為了沒用到的設定查一次資料庫。
 * 後台沒設定完整時就不套用，維持 .env 的既有行為。
 */
final class MailSettings
{
    public const KEYS = [
        'enabled' => 'mail.enabled',
        'host' => 'mail.host',
        'port' => 'mail.port',
        'scheme' => 'mail.scheme',
        'username' => 'mail.username',
        'password' => 'mail.password',
        'from_address' => 'mail.from_address',
        'from_name' => 'mail.from_name',
        'ehlo_domain' => 'mail.ehlo_domain',
        'test_subject' => 'mail.test_subject',
        'test_body' => 'mail.test_body',
    ];

    /** 測試信的預設主旨與內文，後台沒填過時用這個。 */
    public const DEFAULT_TEST_SUBJECT = '【{{ app_name }}】測試信';

    public const DEFAULT_TEST_BODY = <<<'TXT'
        這是一封測試信。

        如果你收到它，表示後台的 SMTP 設定可以正常寄信。

        寄出時間：{{ sent_at }}
        收件信箱：{{ to }}
        TXT;

    public static function testSubject(): string
    {
        $value = trim((string) Setting::get(self::KEYS['test_subject'], ''));

        return $value !== '' ? $value : self::DEFAULT_TEST_SUBJECT;
    }

    public static function testBody(): string
    {
        $value = trim((string) Setting::get(self::KEYS['test_body'], ''));

        return $value !== '' ? $value : self::DEFAULT_TEST_BODY;
    }

    /** 後台是否已啟用寄信。未啟用時所有通知信都不寄。 */
    public static function enabled(): bool
    {
        return (bool) Setting::get(self::KEYS['enabled'], false);
    }

    /**
     * 把後台設定套到 runtime 的 mail config。
     * 主機或寄件者沒填完整就不動，讓 .env 的設定繼續生效。
     */
    public static function apply(): void
    {
        $host = (string) Setting::get(self::KEYS['host'], '');
        $from = (string) Setting::get(self::KEYS['from_address'], '');

        if ($host === '' || $from === '') {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) Setting::get(self::KEYS['port'], 587),
            'mail.mailers.smtp.scheme' => Setting::get(self::KEYS['scheme']) ?: null,
            'mail.mailers.smtp.username' => Setting::get(self::KEYS['username']) ?: null,
            'mail.mailers.smtp.password' => self::password(),
            'mail.from.address' => $from,
            'mail.from.name' => (string) Setting::get(self::KEYS['from_name'], config('app.name')),
            'mail.mailers.smtp.local_domain' => self::ehloDomain($from),
        ]);
    }

    /**
     * EHLO 時宣告的網域。
     *
     * 預設值來自 APP_URL，本機開發時會是 localhost，而 Gmail 之類的服務會以
     * 「421-4.7.0 Try again later (EHLO)」拒絕非 FQDN 的 EHLO。
     * 沒另外設定時就退而用寄件者信箱的網域，至少是個真實存在的名字。
     */
    public static function ehloDomain(string $fromAddress): string
    {
        $configured = trim((string) Setting::get(self::KEYS['ehlo_domain'], ''));

        if ($configured !== '') {
            return $configured;
        }

        $domain = substr(strrchr($fromAddress, '@') ?: '', 1);

        return $domain !== '' ? $domain : (string) config('mail.mailers.smtp.local_domain');
    }

    /** 解密後的 SMTP 密碼；解不開（例如換過 APP_KEY）就當成沒設定。 */
    public static function password(): ?string
    {
        $stored = Setting::get(self::KEYS['password']);

        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null;
        }
    }

    public static function encryptPassword(string $plain): string
    {
        return Crypt::encryptString($plain);
    }
}
