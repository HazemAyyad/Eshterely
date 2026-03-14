<?php

namespace App\Services\Shipping;

/**
 * Structured result for a shipping quote.
 */
final class ShippingQuoteResult
{
    public function __construct(
        public ?string $carrier,
        public bool $warehouseMode,
        public float $actualWeightKg,
        public float $volumetricWeightKg,
        public float $chargeableWeightKg,
        public string $currency,
        public float $finalAmount,
        public array $calculationNotes = [],
        public array $appliedConfigSnapshot = [],
        /** @var list<CarrierQuoteResult> */
        public array $carrierResults = [],
    ) {}

    public function toArray(): array
    {
        return [
            'carrier' => $this->carrier,
            'warehouse_mode' => $this->warehouseMode,
            'actual_weight_kg' => $this->actualWeightKg,
            'volumetric_weight_kg' => $this->volumetricWeightKg,
            'chargeable_weight_kg' => $this->chargeableWeightKg,
            'currency' => $this->currency,
            'final_amount' => $this->finalAmount,
            'calculation_notes' => $this->calculationNotes,
            'applied_config_snapshot' => $this->appliedConfigSnapshot,
            'carrier_results' => array_map(fn (CarrierQuoteResult $r) => $r->toArray(), $this->carrierResults),
        ];
    }
}
