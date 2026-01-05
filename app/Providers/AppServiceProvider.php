<?php

namespace App\Providers;

use App\Models\AuthIP;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        View::composer('*', function ($view) {
          $clientIp = request()->ip();
          
            $isAllowed = AuthIP::select('auth_level')
                ->where('ip', $clientIp)
                ->first();
    
            $hasAccess = $isAllowed && $isAllowed->auth_level >= 5;
            $hasAdminAccess = $isAllowed && $isAllowed->auth_level >= 10;
    
            $view->with(compact('clientIp', 'hasAccess', 'hasAdminAccess'));
        });
    }
}
