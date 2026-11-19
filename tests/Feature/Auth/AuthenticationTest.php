<?php

use App\Models\User;

test('登入頁只提供 SSO', function () {
    config(['services.ncuesa_sso.base_url' => 'https://sso.example.test', 'services.ncuesa_sso.client_id' => 'abc']);

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/Login')->where('ssoConfigured', true));
});

test('沒有密碼登入端點', function () {
    $this->post('/login', ['student_id' => 'x', 'password' => 'y'])->assertMethodNotAllowed();
    $this->get('/forgot-password')->assertNotFound();
});

test('已登入的人不會再看到登入頁', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect();
});

test('沒有設定 SSO 時，登出直接回首頁', function () {
    config(['services.ncuesa_sso.base_url' => null]);

    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});
