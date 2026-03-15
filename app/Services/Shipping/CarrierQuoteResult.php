<?php

namespace App\Services\Shipping;

/**
 * Per-carrier quote result (for multi-carrier comparison and response).
 */
final class CarrierQuoteResult
{
    public function __construct(
        public string $carrier,
        public string $currency,
        public float $amount,
        public array $notes = [],
        public string $pricingMode = 'default',
        public array $breakdown = [],
    ) {}

    public function toArray(): array
    {
        return [
            'carrier' => $this->carrier,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'notes' => $this->notes,
            'pricing_mode' => $this->pricingMode,
            'calculation_breakdown' => $this->breakdown,
        ];
    }
}
