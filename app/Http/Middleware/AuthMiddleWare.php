<?php

namespace App\Http\Middleware;

use App\Models\AuthIp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthMiddleWare
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $requireLevel): Response
    {
        $clientIp = $request->ip();

        $isAllowed = AuthIp::select('auth_level')
            ->where('ip', $clientIp)
            ->first();
        if (!$isAllowed || $isAllowed->auth_level < $requireLevel) {
            return abort(403);
        }

        // 如果通過驗證，繼續處理請求
        return $next($request);
    }
}
