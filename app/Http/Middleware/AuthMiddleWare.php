<?php

namespace App\Http\Middleware;

use App\Models\AuthIP;
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
          $clientIp= $request->ip();

        // 無需權限檢查
        if ((int)$requireLevel == 0) {
            return $next($request);
        }

        // 查詢權限等級
        $isAllowed = AuthIP::select('auth_level')
            ->where('ip', $clientIp)
            ->first();

        // 驗證權限是否足夠
        if (!$isAllowed || $isAllowed->auth_level < $requireLevel) {
            return abort(403, '您沒有權限訪問該頁面');
        }

        return $next($request);
    }
}
