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
        View::composer('template', function ($view) {
            $hasAccess = session('hasAccess', false);
            $hasAdminAccess = session('hasAdminAccess', false);
            $view->with(compact('hasAccess', 'hasAdminAccess'));
        });
    }
}
