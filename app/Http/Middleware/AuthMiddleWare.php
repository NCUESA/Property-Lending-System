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
        $clientIp = $request->ip();

        if((int)$requireLevel == 0){
            return $next($request);
        }
        $isAllowed = AuthIP::select('auth_level')
            ->where('ip', $clientIp)
            ->first();
        if (!$isAllowed || $isAllowed->auth_level < $requireLevel) {
            return abort(403);
        }
        
        // With Permition IP will generate URL to admin page
        session(['hasAccess' => true]);
       
        if ((int)$requireLevel == 10) {
            session(['hasAdminAccess' => true]);
        }
        $request->session()->save();
        // 如果通過驗證，繼續處理請求
        return $next($request);
    }
}
