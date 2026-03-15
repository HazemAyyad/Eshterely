<?php

namespace App\Services\Shipping;

use App\Services\Shipping\Contracts\CarrierSelectionStrategyInterface;

/**
 * Selects the carrier with the lowest quote amount.
 * Used when carrier = auto.
 */
class CheapestCarrierSelectionStrategy implements CarrierSelectionStrategyInterface
{
    public function select(array $results): ?CarrierPricingResult
    {
        if ($results === []) {
            return null;
        }
        $best = $results[0];
        foreach ($results as $r) {
            if ($r->amount < $best->amount) {
                $best = $r;
            }
        }
        return $best;
    }
}
