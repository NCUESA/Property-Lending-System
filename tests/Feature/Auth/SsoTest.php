<?php

use App\Auth\NcuesaSsoUser;
use App\Enums\Role;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

beforeEach(function () {
    config([
        'services.ncuesa_sso.base_url' => 'https://sso.example.test',
        'services.ncuesa_sso.realm' => 'NCUESA',
        'services.ncuesa_sso.client_id' => 'property',
    ]);
});

function fakeSsoUser(array $attrs): void
{
    $user = new NcuesaSsoUser;
    $user->idToken = $attrs['id_token'] ?? null;
    $user->map([
        'id' => $attrs['id'],
        'name' => $attrs['name'] ?? '王小明',
        'email' => $attrs['email'] ?? null,
        'nickname' => $attrs['student_id'] ?? null,
    ]);

    Socialite::shouldReceive('driver->user')->andReturn($user);
}

it('導向 Keycloak 授權頁，帶 PKCE 與 openid scope', function () {
    $location = $this->get(route('auth.sso.redirect'))->assertRedirect()->headers->get('Location');

    expect($location)->toStartWith('https://sso.example.test/realms/NCUESA/protocol/openid-connect/auth?')
        ->toContain('client_id=property')
        ->toContain('scope=openid+profile+email')
        ->toContain('code_challenge_method=S256');
});

it('登入時保存 id_token，登出時一併登出 Keycloak', function () {
    fakeSsoUser(['id' => 'kc-uuid-1', 'email' => 'a@example.com', 'id_token' => 'the-id-token']);
    $this->get(route('auth.sso.callback'));

    $location = $this->post(route('logout'))->assertRedirect()->headers->get('Location');

    $this->assertGuest();
    expect($location)->toStartWith('https://sso.example.test/realms/NCUESA/protocol/openid-connect/logout?')
        ->toContain('id_token_hint=the-id-token')
        ->toContain('post_logout_redirect_uri='.urlencode(route('home')));
});

it('Inertia 請求登出時用 409 + X-Inertia-Location 整頁跳轉', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'), [], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location');
});

it('可以設定學號對應的 claim', function () {
    config(['services.ncuesa_sso.student_id_claim' => 'preferred_username']);
    $provider = Socialite::driver('ncuesa');
    $map = (fn (array $u) => $this->mapUserToObject($u))->call($provider, ['sub' => 'x', 'preferred_username' => 'S1234567']);

    expect($map->getNickname())->toBe('S1234567');
});

it('第一次登入自動建立借用人帳號', function () {
    fakeSsoUser(['id' => 'kc-uuid-42', 'email' => 'new@example.com']);

    $this->get(route('auth.sso.callback', ['code' => 'x', 'state' => 'y']))->assertRedirect(route('home'));

    $user = User::sole();
    expect($user->sso_id)->toBe('kc-uuid-42')
        ->and($user->role)->toBe(Role::Borrower)
        ->and($user->password)->toBeNull();
    $this->assertAuthenticatedAs($user);
});

it('管理員預先建立的帳號以 email 綁定，保留角色', function () {
    $staff = User::factory()->staff()->create(['email' => 'staff@example.com', 'sso_id' => null]);
    fakeSsoUser(['id' => 7, 'email' => 'staff@example.com']);

    $this->get(route('auth.sso.callback'));

    expect($staff->fresh()->sso_id)->toBe('7')->and(User::count())->toBe(1);
    $this->assertAuthenticatedAs($staff);
});

it('停用的帳號不能登入', function () {
    User::factory()->inactive()->create(['sso_id' => '9']);
    fakeSsoUser(['id' => 9]);

    $this->get(route('auth.sso.callback'))
        ->assertRedirect(route('login'))
        ->assertInertiaFlash('toast.message', '此帳號已停用，請聯絡學生會');
    $this->assertGuest();
});

it('state 過期時回登入頁並提示，而不是 500', function () {
    Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

    $this->get(route('auth.sso.callback'))
        ->assertRedirect(route('login'))
        ->assertInertiaFlash('toast.type', 'error');
});

it('使用者在 SSO 取消授權', function () {
    $this->get(route('auth.sso.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('login'))
        ->assertInertiaFlash('toast.message', 'SSO 登入已取消');
});
