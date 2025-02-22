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
          $clientIp= '127.0.0.1';
          if (isset($_SERVER['HTTP_CF_CONNECTING_IPV6'])) {
            $clientIp = $_SERVER['HTTP_CF_CONNECTING_IPV6'];
          } elseif (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'];
          } elseif (isset($_SERVER["HTTP_X_REAL_IP"])) {
            $clientIp = $_SERVER["HTTP_X_REAL_IP"];
          } elseif (isset($_SERVER['HTTP_X_FORWARED_FOR'])) {
            $clientIp = $_SERVER['HTTP_X_FORWARED_FOR'];
          } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $clientIp = $_SERVER['HTTP_CLIENT_IP'];
          } else {
            if (isset($_SERVER['REMOTE_ADDR']))
              $clientIp = $_SERVER['REMOTE_ADDR'];
          }
            $isAllowed = AuthIP::select('auth_level')
                ->where('ip', $clientIp)
                ->first();
    
            $hasAccess = $isAllowed && $isAllowed->auth_level >= 5;
            $hasAdminAccess = $isAllowed && $isAllowed->auth_level >= 10;
    
            $view->with(compact('clientIp', 'hasAccess', 'hasAdminAccess'));
        });
    }
}
