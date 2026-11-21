<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 沒有 cookie 就預設淺色（前端預設值在 useAppearance.ts 的 DEFAULT_APPEARANCE）
        View::share('appearance', $request->cookie('appearance') ?? 'light');

        return $next($request);
    }
}
