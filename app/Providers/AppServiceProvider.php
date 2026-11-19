<?php

namespace App\Providers;

use App\Auth\NcuesaSsoProvider;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\SocialiteManager;

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
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureSso();
    }

    /**
     * 角色權限：staff 可處理借用單；admin 另可維護器材、人員、群組與設定。停用的帳號一律沒有權限。
     */
    protected function configureAuthorization(): void
    {
        Gate::define('staff', fn (User $user): bool => $user->is_active && $user->isStaffOrAbove());
        Gate::define('admin', fn (User $user): bool => $user->is_active && $user->isAdmin());
    }

    protected function configureSso(): void
    {
        $this->app->afterResolving(Socialite::class, function (SocialiteManager $socialite) {
            $socialite->extend('ncuesa', fn () => $socialite->buildProvider(NcuesaSsoProvider::class, [
                'client_id' => config('services.ncuesa_sso.client_id'),
                'client_secret' => config('services.ncuesa_sso.client_secret'),
                'redirect' => config('services.ncuesa_sso.redirect'),
            ])->scopes(config('services.ncuesa_sso.scopes')));
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );
    }
}
