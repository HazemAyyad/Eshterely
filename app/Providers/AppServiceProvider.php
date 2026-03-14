<?php

namespace App\Providers;

use App\Services\Payments\SquareService;
use App\Services\Shipping\PackageNormalizer;
use App\Services\Shipping\ShippingPricingConfigService;
use App\Services\Shipping\ShippingQuoteService;
use App\Services\Shipping\VolumetricWeightCalculator;
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

        $this->app->singleton(ShippingPricingConfigService::class);
        $this->app->singleton(PackageNormalizer::class);
        $this->app->singleton(VolumetricWeightCalculator::class, function ($app) {
            return new VolumetricWeightCalculator($app->make(ShippingPricingConfigService::class));
        });
        $this->app->singleton(ShippingQuoteService::class, function ($app) {
            return new ShippingQuoteService(
                $app->make(PackageNormalizer::class),
                $app->make(VolumetricWeightCalculator::class),
                $app->make(ShippingPricingConfigService::class)
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
