<?php

namespace App\Services\Shipping\Contracts;

use App\Services\Shipping\CarrierPricingContext;
use App\Services\Shipping\CarrierPricingResult;

/**
 * Resolves carrier-specific pricing for a normalized package.
 * Implementations apply warehouse vs direct logic and config-based parameters.
 */
interface CarrierPricingResolverInterface
{
    /**
     * Whether this resolver supports the given carrier key (e.g. 'dhl', 'ups', 'fedex').
     */
    public function supportsCarrier(string $carrier): bool;

    /**
     * Resolve price for the given context.
     * Uses admin config for volumetric divisor, discount %, warehouse fee, min charge, markup, rounding.
     */
    public function resolve(CarrierPricingContext $context): CarrierPricingResult;
}
