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

        $this->app->bind(
            \Osiset\ShopifyApp\Actions\GetPlanUrl::class,
            \App\Actions\GetPlanUrl::class
        );

        $this->app->bind(
            \Osiset\ShopifyApp\Actions\ActivatePlan::class,
            function ($app) {
                return new \App\Actions\ActivatePlan(
                    $app->make(\Osiset\ShopifyApp\Actions\CancelCurrentPlan::class),
                    $app->make(\Osiset\ShopifyApp\Services\ChargeHelper::class),
                    $app->make(\Osiset\ShopifyApp\Contracts\Queries\Shop::class),
                    $app->make(\Osiset\ShopifyApp\Contracts\Queries\Plan::class),
                    $app->make(\Osiset\ShopifyApp\Contracts\Commands\Charge::class),
                    $app->make(\Osiset\ShopifyApp\Contracts\Commands\Shop::class)
                );
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production' || request()->header('X-Forwarded-Proto') === 'https' || !empty(env('APP_URL'))) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
