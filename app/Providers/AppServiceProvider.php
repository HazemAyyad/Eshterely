<?php

namespace App\Providers;

use App\Services\Payments\SquareService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SquareService::class, function () {
            return new SquareService(
                accessToken: (string) config('square.access_token'),
                locationId: (string) config('square.location_id'),
                environment: (string) config('square.environment'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
