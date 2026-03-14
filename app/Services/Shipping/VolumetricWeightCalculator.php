<?php

namespace App\Services\Shipping;

/**
 * Volumetric (dimensional) weight calculation.
 * Formula: (L × W × H) / divisor = volumetric weight.
 * Chargeable weight = max(actual weight, volumetric weight).
 *
 * Divisor is admin-configurable (e.g. 5000 for cm/kg, 139 for in/lb).
 * When divisor is not set or invalid, falls back to 5000 (common for cm/kg).
 */
final class VolumetricWeightCalculator
{
    /** Default divisor when not configured (cm/kg convention). Documented for clarity. */
    public const DEFAULT_DIVISOR = 5000.0;

    public function __construct(
        private ShippingPricingConfigService $config
    ) {}

    /**
     * Volumetric weight from dimensions in cm, using divisor from config or default.
     */
    public function volumetricWeightKg(float $lengthCm, float $widthCm, float $heightCm): float
    {
        $divisor = $this->config->volumetricDivisor();
        if ($divisor <= 0) {
            $divisor = self::DEFAULT_DIVISOR;
        }
        $volumeCm3 = $lengthCm * $widthCm * $heightCm;

        return $volumeCm3 / $divisor;
    }

    /**
     * Chargeable weight = max(actual weight, volumetric weight).
     * Weights must be in the same unit (kg).
     */
    public function chargeableWeightKg(float $actualWeightKg, float $volumetricWeightKg): float
    {
        return max($actualWeightKg, $volumetricWeightKg);
    }

    /**
     * Returns [volumetric_kg, chargeable_kg] for the given package.
     */
    public function compute(float $actualWeightKg, float $lengthCm, float $widthCm, float $heightCm): array
    {
        $volumetricKg = $this->volumetricWeightKg($lengthCm, $widthCm, $heightCm);
        $chargeableKg = $this->chargeableWeightKg($actualWeightKg, $volumetricKg);

        return [
            'volumetric_kg' => $volumetricKg,
            'chargeable_kg' => $chargeableKg,
        ];
    }
}
