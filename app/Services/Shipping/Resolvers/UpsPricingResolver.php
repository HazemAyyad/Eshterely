<?php

namespace App\Services\Shipping\Resolvers;

/**
 * UPS carrier pricing. Warehouse vs direct logic and all parameters from admin config.
 */
class UpsPricingResolver extends AbstractCarrierPricingResolver
{
    protected function carrierKey(): string
    {
        return 'ups';
    }

    protected function pricingModeName(): string
    {
        return 'ups_zone';
    }
}
