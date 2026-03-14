<?php

namespace App\Services\Shipping;

/**
 * Normalized package input for shipping calculations.
 * All weight and dimension values are stored in canonical units (kg, cm) after normalization.
 */
final class NormalizedPackageInput
{
    public function __construct(
        public string $destinationCountry,
        public ?string $carrier,
        public bool $warehouseMode,
        /** Weight in kg */
        public float $weightKg,
        /** Length in cm */
        public float $lengthCm,
        /** Width in cm */
        public float $widthCm,
        /** Height in cm */
        public float $heightCm,
        public int $quantity = 1,
    ) {}

    public function toArray(): array
    {
        return [
            'destination_country' => $this->destinationCountry,
            'carrier' => $this->carrier,
            'warehouse_mode' => $this->warehouseMode,
            'weight_kg' => $this->weightKg,
            'length_cm' => $this->lengthCm,
            'width_cm' => $this->widthCm,
            'height_cm' => $this->heightCm,
            'quantity' => $this->quantity,
        ];
    }
}
