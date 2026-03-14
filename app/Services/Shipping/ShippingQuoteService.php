<?php

namespace App\Services\Shipping;

/**
 * Main entry for shipping quotes.
 * Wires normalized input, weight/volumetric calculation, and config.
 * Does not implement full DHL/UPS/FedEx pricing tables yet; prepares structure for future carrier pricing.
 */
class ShippingQuoteService
{
    public function __construct(
        private PackageNormalizer $normalizer,
        private VolumetricWeightCalculator $volumetricCalculator,
        private ShippingPricingConfigService $config
    ) {}

    /**
     * Build a quote from raw input.
     * Validates and normalizes input, computes weights, applies config-based logic.
     */
    public function quote(array $input): ShippingQuoteResult
    {
        $package = $this->normalizer->normalize($input);
        $totalWeightKg = $package->weightKg * $package->quantity;
        $weights = $this->volumetricCalculator->compute(
            $package->weightKg,
            $package->lengthCm,
            $package->widthCm,
            $package->heightCm
        );
        $volumetricKg = $weights['volumetric_kg'];
        $chargeableKg = $weights['chargeable_kg'];
        if ($package->quantity > 1) {
            $volumetricKg *= $package->quantity;
            $chargeableKg *= $package->quantity;
        }

        $currency = $this->config->defaultCurrency();
        $minCharge = $this->config->minShippingCharge();
        $warehouseFee = $package->warehouseMode ? $this->config->warehouseHandlingFee() : 0.0;
        $multiPackagePercent = ($package->quantity > 1) ? $this->config->multiPackagePercent() : 0.0;

        // Simple calculation path: base from chargeable weight (e.g. per-kg rate could come from carrier later).
        // For foundation we use a minimal formula: min_charge + warehouse_fee + (chargeable_kg * 0) so amount is at least min + fee.
        // A real per-kg rate would be configurable or from carrier tables; not hardcoding that here.
        $baseAmount = $minCharge + $warehouseFee;
        $markup = $this->config->defaultMarkupPercent();
        $amountAfterMarkup = $baseAmount * (1 + $markup / 100);
        $amountAfterMultiPackage = $multiPackagePercent > 0
            ? $amountAfterMarkup * (1 + $multiPackagePercent / 100)
            : $amountAfterMarkup;
        $finalAmount = max($minCharge, $amountAfterMultiPackage);

        $notes = [];
        $notes[] = sprintf('Actual weight: %.3f kg', $totalWeightKg);
        $notes[] = sprintf('Volumetric weight: %.3f kg', $volumetricKg);
        $notes[] = sprintf('Chargeable weight: %.3f kg', $chargeableKg);
        if ($package->warehouseMode && $warehouseFee > 0) {
            $notes[] = sprintf('Warehouse handling fee: %s %.2f', $currency, $warehouseFee);
        }
        if ($package->quantity > 1 && $multiPackagePercent > 0) {
            $notes[] = sprintf('Multi-package adjustment: +%s%%', (string) $multiPackagePercent);
        }

        $configSnapshot = $this->config->snapshotForQuote();

        $carrierResults = [];
        $carrierSlug = $package->carrier ?? 'default';
        $carrierResults[] = new CarrierQuoteResult(
            carrier: $carrierSlug,
            currency: $currency,
            amount: $finalAmount,
            notes: $notes
        );

        return new ShippingQuoteResult(
            carrier: $package->carrier,
            warehouseMode: $package->warehouseMode,
            actualWeightKg: $totalWeightKg,
            volumetricWeightKg: $volumetricKg,
            chargeableWeightKg: $chargeableKg,
            currency: $currency,
            finalAmount: round($finalAmount, 2),
            calculationNotes: $notes,
            appliedConfigSnapshot: $configSnapshot,
            carrierResults: $carrierResults
        );
    }
}
