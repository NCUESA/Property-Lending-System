<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('synology')->redirect();
    }

    public function callback()
    {
        try {
            $synologyUser = Socialite::driver('synology')->user();
        } catch (\Exception $e) {
            return redirect('/')->withErrors(['msg' => 'OAuth Login Failed']);
        }

        // Check if user exists, or create a new one
        $user = User::firstOrCreate(
            ['provider_id' => $synologyUser->getId()],
            [
                'student_id' => $synologyUser->getNickname(),
                // Use nickname as fallback if name is null
                'name' => $synologyUser->getName() ?? $synologyUser->getNickname() ?? 'Unknown User',
                'level' => 'muggle',
                'status' => 'u',
                'password' => null
            ]
        );

        Auth::login($user);

        return redirect('/status');
    }
    
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}