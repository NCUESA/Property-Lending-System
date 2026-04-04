<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleAuth
{
    public function handle(Request $request, Closure $next, $requiredLevel)
    {
        // Check if user is logged in
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        $userLevelValue = 0;

        // Convert string level to integer for comparison
        switch ($user->level) {
            case 'admin':
                $userLevelValue = 10;
                break;
            case 'normal':
                $userLevelValue = 5;
                break;
            case 'muggle':
                $userLevelValue = 0;
                break;
        }

        // Block access if level is insufficient
        if ($userLevelValue < (int) $requiredLevel) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}