<?php

namespace App\Providers;

use App\Services\DailyActionLimiter;
use App\Services\GameApiClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GameApiClient::class);
        $this->app->singleton(DailyActionLimiter::class);
    }

    public function boot(): void
    {
        //
    }
}
