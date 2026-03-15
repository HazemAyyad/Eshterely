<?php

namespace App\Providers;

use App\Services\Payments\SquareService;
use App\Services\Shipping\CarrierPricingResolverRegistry;
use App\Services\Shipping\CheapestCarrierSelectionStrategy;
use App\Services\Shipping\Contracts\CarrierSelectionStrategyInterface;
use App\Services\Shipping\PackageNormalizer;
use App\Services\Shipping\Resolvers\DhlPricingResolver;
use App\Services\Shipping\Resolvers\FedexPricingResolver;
use App\Services\Shipping\Resolvers\UpsPricingResolver;
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
        $this->app->singleton(CarrierPricingResolverRegistry::class, function ($app) {
            $registry = new CarrierPricingResolverRegistry();
            $config = $app->make(ShippingPricingConfigService::class);
            $registry->register(new DhlPricingResolver($config));
            $registry->register(new UpsPricingResolver($config));
            $registry->register(new FedexPricingResolver($config));

            return $registry;
        });
        $this->app->singleton(CarrierSelectionStrategyInterface::class, CheapestCarrierSelectionStrategy::class);
        $this->app->singleton(ShippingQuoteService::class, function ($app) {
            return new ShippingQuoteService(
                $app->make(PackageNormalizer::class),
                $app->make(VolumetricWeightCalculator::class),
                $app->make(ShippingPricingConfigService::class),
                $app->make(CarrierPricingResolverRegistry::class),
                $app->make(CarrierSelectionStrategyInterface::class)
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
