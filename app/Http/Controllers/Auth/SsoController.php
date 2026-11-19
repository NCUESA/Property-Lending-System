<?php

namespace App\Http\Controllers\Auth;

use App\Auth\NcuesaSsoProvider;
use App\Auth\NcuesaSsoUser;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SsoController extends Controller
{
    /** 登入頁：只有 SSO 一種登入方式。 */
    public function create(): Response
    {
        return Inertia::render('auth/Login', [
            'ssoConfigured' => filled(config('services.ncuesa_sso.base_url')) && filled(config('services.ncuesa_sso.client_id')),
        ]);
    }

    /**
     * 登出本系統，並一併登出 Keycloak。
     * 值班電腦是共用的：只登出本系統的話，下一個人按「登入」會直接以上一個人的 SSO 身分登入。
     */
    public function destroy(Request $request): SymfonyResponse
    {
        $idToken = $request->session()->get('sso_id_token');

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (blank(config('services.ncuesa_sso.base_url'))) {
            return to_route('home');
        }

        // 外部網址：Inertia 請求要用 Inertia::location 才會整頁跳轉
        return Inertia::location(NcuesaSsoProvider::logoutUrl(route('home'), $idToken));
    }

    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('ncuesa')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return to_route('login')->with('error', 'SSO 登入已取消');
        }

        try {
            $ssoUser = Socialite::driver('ncuesa')->user();
        } catch (InvalidStateException) {
            return to_route('login')->with('error', '登入逾時，請重新登入');
        }

        $user = $this->resolveUser(
            ssoId: (string) $ssoUser->getId(),
            name: $ssoUser->getName(),
            email: $ssoUser->getEmail(),
            studentId: $ssoUser->getNickname(),
        );

        if (! $user->is_active) {
            return to_route('login')->with('error', '此帳號已停用，請聯絡學生會');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $request->session()->put('sso_id_token', $ssoUser instanceof NcuesaSsoUser ? $ssoUser->idToken : null);

        return redirect()->intended(route('home'));
    }

    /**
     * 依序以 sso_id、email、學號對應既有帳號（admin 預先建立的帳號第一次登入時綁定 sso_id），
     * 都對不到就建立新的借用人帳號。
     */
    private function resolveUser(string $ssoId, ?string $name, ?string $email, ?string $studentId): User
    {
        $user = User::where('sso_id', $ssoId)->first()
            ?? ($email ? User::whereNull('sso_id')->where('email', $email)->first() : null)
            ?? ($studentId ? User::whereNull('sso_id')->where('student_id', $studentId)->first() : null);

        if ($user === null) {
            return User::create([
                'sso_id' => $ssoId,
                'name' => $name ?? $email ?? $ssoId,
                'email' => $email,
                'student_id' => $studentId,
                'role' => Role::Borrower,
            ]);
        }

        $user->forceFill([
            'sso_id' => $ssoId,
            'email' => $user->email ?? $email,
            'student_id' => $user->student_id ?? $studentId,
        ])->save();

        return $user;
    }
}
