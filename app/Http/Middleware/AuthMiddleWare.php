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
          $clientIp= '127.0.0.1';
          if (isset($_SERVER['HTTP_CF_CONNECTING_IPV6'])) {
            $clientIp = $_SERVER['HTTP_CF_CONNECTING_IPV6'];
          } elseif (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'];
          } elseif (isset($_SERVER["HTTP_X_REAL_IP"])) {
            $clientIp = $_SERVER["HTTP_X_REAL_IP"];
          } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $clientIp = $_SERVER('HTTP_X_FORWARDED_FOR');
            $clientIps = explode(',', $clientIp);
            $clientIp = $clientIps[0];
          } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $clientIp = $_SERVER['HTTP_CLIENT_IP'];
          } else {
            if (isset($_SERVER['REMOTE_ADDR']))
              $clientIp = $_SERVER['REMOTE_ADDR'];
          }

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
