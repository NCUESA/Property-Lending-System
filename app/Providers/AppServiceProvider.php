<?php

namespace App\Providers;

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
            $hasAccess = session('hasAccess', false);
            $hasAdminAccess = session('hasAdminAccess', false);
            $clientIp = request()->ip(); // 獲取用戶 IP
            $view->with(compact('hasAccess', 'hasAdminAccess', 'clientIp'));
        });
    }
}
