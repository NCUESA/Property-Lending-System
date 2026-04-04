<?php

namespace App\Providers;

use App\Models\AuthIP;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Extensions\SynologyProvider;
use Laravel\Socialite\Facades\Socialite;

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

        Socialite::extend('synology', function ($app) {
            $config = $app['config']['services.synology'];

            return new SynologyProvider(
                $app['request'],
                $config['client_id'],
                $config['client_secret'],
                $config['redirect']
            );
        });
    }
}
