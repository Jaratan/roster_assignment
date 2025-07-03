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
        //
        $this->app->singleton(BehanceService::class);
        $this->app->singleton(YouTubeService::class);
        $this->app->singleton(VimeoService::class);
        $this->app->singleton(PortfolioServiceResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
