<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \Osiset\ShopifyApp\Actions\InstallShop::class,
            \App\Actions\CustomInstallShop::class
        );

        $this->app->bind(
            \Osiset\ShopifyApp\Actions\AuthenticateShop::class,
            \App\Actions\CustomAuthenticateShop::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
