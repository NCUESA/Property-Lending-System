<?php

namespace App\Auth;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

/**
 * NCUESA SSO（Keycloak）的 OpenID Connect authorization code flow，啟用 PKCE。
 */
class NcuesaSsoProvider extends AbstractProvider
{
    protected $scopeSeparator = ' ';

    protected $usesPKCE = true;

    public static function realmUrl(): string
    {
        return rtrim((string) config('services.ncuesa_sso.base_url'), '/')
            .'/realms/'.rawurlencode((string) config('services.ncuesa_sso.realm'))
            .'/protocol/openid-connect';
    }

    /**
     * Keycloak 登出網址（RP-initiated logout）。登出後導回 $redirectTo，
     * 該網址必須登記在 Keycloak client 的 Valid post logout redirect URIs。
     */
    public static function logoutUrl(string $redirectTo, ?string $idToken): string
    {
        return self::realmUrl().'/logout?'.http_build_query(array_filter([
            'client_id' => config('services.ncuesa_sso.client_id'),
            'post_logout_redirect_uri' => $redirectTo,
            'id_token_hint' => $idToken,
        ]));
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(self::realmUrl().'/auth', $state);
    }

    protected function getTokenUrl(): string
    {
        return self::realmUrl().'/token';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(self::realmUrl().'/userinfo', [
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * Keycloak userinfo：sub、name、preferred_username、email……
     * 預設沒有學號；若有對應的 claim，以 NCUESA_SSO_STUDENT_ID_CLAIM 指定。
     *
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        $studentIdClaim = config('services.ncuesa_sso.student_id_claim');

        return (new NcuesaSsoUser)->setRaw($user)->map([
            'id' => $user['sub'] ?? null,
            'name' => $this->displayName($user),
            'email' => $user['email'] ?? null,
            'nickname' => $studentIdClaim ? ($user[$studentIdClaim] ?? null) : null,
        ]);
    }

    /**
     * Keycloak 的 name claim 是「given_name family_name」（名在前），中文姓名要倒過來，
     * 所以改用 family_name + given_name 自行組合，中間不加空白。
     * 兩個 claim 都拿不到時才退回 name / preferred_username。
     *
     * @param  array<string, mixed>  $user
     */
    private function displayName(array $user): ?string
    {
        $family = trim((string) ($user['family_name'] ?? ''));
        $given = trim((string) ($user['given_name'] ?? ''));

        if ($family !== '' || $given !== '') {
            return $family.$given;
        }

        return $user['name'] ?? $user['preferred_username'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $user
     */
    protected function userInstance(array $response, array $user): User
    {
        $instance = parent::userInstance($response, $user);

        if ($instance instanceof NcuesaSsoUser) {
            $instance->idToken = Arr::get($response, 'id_token');
        }

        return $instance;
    }
}
