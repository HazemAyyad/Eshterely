<?php

namespace App\Services\Shipping\Resolvers;

/**
 * DHL carrier pricing. Warehouse vs direct logic and all parameters from admin config.
 */
class DhlPricingResolver extends AbstractCarrierPricingResolver
{
    protected function carrierKey(): string
    {
        return 'dhl';
    }

    protected function pricingModeName(): string
    {
        return 'dhl_zone';
    }
}
