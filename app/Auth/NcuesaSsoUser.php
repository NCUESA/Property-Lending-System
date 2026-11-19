<?php

namespace App\Auth;

use Laravel\Socialite\Two\User;

/**
 * 多保存 OIDC 的 id_token，登出 Keycloak 時當作 id_token_hint，避免出現「確定要登出嗎」確認頁。
 */
class NcuesaSsoUser extends User
{
    public ?string $idToken = null;
}
