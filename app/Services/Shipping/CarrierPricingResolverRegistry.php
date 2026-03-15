<?php

namespace App\Services\Shipping;

use App\Services\Shipping\Contracts\CarrierPricingResolverInterface;

/**
 * Registry of carrier pricing resolvers. Resolves carrier key to resolver instance.
 */
class CarrierPricingResolverRegistry
{
    /** @var array<string, CarrierPricingResolverInterface> */
    private array $resolvers = [];

    public function register(CarrierPricingResolverInterface $resolver): void
    {
        foreach (['dhl', 'ups', 'fedex'] as $carrier) {
            if ($resolver->supportsCarrier($carrier)) {
                $this->resolvers[strtolower($carrier)] = $resolver;
                break;
            }
        }
    }

    public function get(string $carrier): ?CarrierPricingResolverInterface
    {
        $key = strtolower($carrier);

        return $this->resolvers[$key] ?? null;
    }

    /**
     * @return list<CarrierPricingResolverInterface>
     */
    public function all(): array
    {
        return array_values($this->resolvers);
    }

    /**
     * Supported carrier keys (e.g. ['dhl', 'ups', 'fedex']).
     *
     * @return list<string>
     */
    public function supportedCarriers(): array
    {
        return array_keys($this->resolvers);
    }
}
