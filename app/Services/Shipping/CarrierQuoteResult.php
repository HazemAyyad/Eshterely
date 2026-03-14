<?php

namespace App\Services\Shipping;

/**
 * Per-carrier quote result (for future multi-carrier comparison).
 */
final class CarrierQuoteResult
{
    public function __construct(
        public string $carrier,
        public string $currency,
        public float $amount,
        public array $notes = [],
    ) {}

    public function toArray(): array
    {
        return [
            'carrier' => $this->carrier,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'notes' => $this->notes,
        ];
    }
}
