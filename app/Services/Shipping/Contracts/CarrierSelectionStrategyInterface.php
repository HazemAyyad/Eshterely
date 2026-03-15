<?php

namespace App\Services\Shipping\Contracts;

use App\Services\Shipping\CarrierPricingResult;

/**
 * Strategy for selecting one carrier from multiple quote results.
 * Current implementation: cheapest carrier. Future extensions can support
 * recommended carrier, ETA-based filtering, country restrictions, carrier priority rules.
 */
interface CarrierSelectionStrategyInterface
{
    /**
     * Select the preferred result from multiple carrier quotes.
     *
     * @param  list<CarrierPricingResult>  $results
     */
    public function select(array $results): ?CarrierPricingResult;
}
