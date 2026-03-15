<?php

namespace App\Services\Shipping\Resolvers;

/**
 * FedEx carrier pricing. Warehouse vs direct logic and all parameters from admin config.
 */
class FedexPricingResolver extends AbstractCarrierPricingResolver
{
    protected function carrierKey(): string
    {
        return 'fedex';
    }

    protected function pricingModeName(): string
    {
        return 'fedex_zone';
    }
}
