<?php

namespace App\Services\Shipping\Resolvers;

use App\Services\Shipping\CarrierPricingContext;
use App\Services\Shipping\CarrierPricingResult;
use App\Services\Shipping\Contracts\CarrierPricingResolverInterface;
use App\Services\Shipping\Contracts\ShippingZoneRepositoryInterface;
use App\Services\Shipping\ShippingPricingConfigService;

/**
 * Base resolver: applies config-based min charge, warehouse fee, discount, markup, multi-package.
 * Subclasses define carrier key and pricing mode; zone/rate tables can be added later.
 */
abstract class AbstractCarrierPricingResolver implements CarrierPricingResolverInterface
{
    public function __construct(
        protected ShippingPricingConfigService $config,
        protected ShippingZoneRepositoryInterface $zones
    ) {}

    abstract protected function carrierKey(): string;

    abstract protected function pricingModeName(): string;

    public function supportsCarrier(string $carrier): bool
    {
        return strtolower($carrier) === $this->carrierKey();
    }

    public function resolve(CarrierPricingContext $context): CarrierPricingResult
    {
        $chargeableKg = $this->applyRounding($context->chargeableWeightKg);
        $totalChargeableKg = $chargeableKg * $context->quantity;

        $currency = $this->config->defaultCurrency();
        $minCharge = $this->config->minShippingCharge();
        $warehouseFee = $context->warehouseMode ? $this->config->warehouseHandlingFee() : 0.0;
        $discountPercent = $this->config->carrierDiscountPercent($context->carrier);
        $markupPercent = $this->config->defaultMarkupPercent();
        $multiPackagePercent = $context->quantity > 1 ? $this->config->multiPackagePercent() : 0.0;

        // Base from chargeable weight: use per-kg rate from zone or default minimal base
        $baseFromWeight = $this->getBaseRateForWeight($context, $totalChargeableKg);
        $baseAmount = $minCharge + $warehouseFee + $baseFromWeight;

        $afterDiscount = $discountPercent > 0
            ? $baseAmount * (1 - $discountPercent / 100)
            : $baseAmount;
        $afterMarkup = $markupPercent > 0
            ? $afterDiscount * (1 + $markupPercent / 100)
            : $afterDiscount;
        $afterMultiPackage = $multiPackagePercent > 0
            ? $afterMarkup * (1 + $multiPackagePercent / 100)
            : $afterMarkup;
        $finalAmount = max($minCharge, round($afterMultiPackage, 2));

        $breakdown = [
            'min_charge' => $minCharge,
            'warehouse_fee' => $warehouseFee,
            'base_from_weight' => $baseFromWeight,
            'discount_percent' => $discountPercent,
            'markup_percent' => $markupPercent,
            'multi_package_percent' => $multiPackagePercent,
            'chargeable_kg' => $totalChargeableKg,
        ];

        $notes = [];
        $notes[] = sprintf('Actual weight: %.3f kg', $context->actualWeightKg * $context->quantity);
        $notes[] = sprintf('Volumetric weight: %.3f kg', $context->volumetricWeightKg * $context->quantity);
        $notes[] = sprintf('Chargeable weight: %.3f kg', $totalChargeableKg);
        if ($context->warehouseMode && $warehouseFee > 0) {
            $notes[] = sprintf('Warehouse handling fee: %s %.2f', $currency, $warehouseFee);
        }
        if ($discountPercent > 0) {
            $notes[] = sprintf('Carrier discount: -%s%%', (string) $discountPercent);
        }
        if ($context->quantity > 1 && $multiPackagePercent > 0) {
            $notes[] = sprintf('Multi-package adjustment: +%s%%', (string) $multiPackagePercent);
        }

        return new CarrierPricingResult(
            carrier: $context->carrier,
            currency: $currency,
            amount: $finalAmount,
            pricingMode: $this->pricingModeName(),
            breakdown: $breakdown,
            notes: $notes
        );
    }

    protected function getBaseRateForWeight(CarrierPricingContext $context, float $totalChargeableKg): float
    {
        $info = $this->zones->getZoneRateInfo(
            $context->carrier,
            $context->destinationCountry,
            null,
            $totalChargeableKg
        );

        return $info?->baseRate ?? 0.0;
    }

    protected function applyRounding(float $chargeableKg): float
    {
        $strategy = $this->config->roundingStrategy();
        return match ($strategy) {
            ShippingPricingConfigService::ROUNDING_UP_500G => ceil($chargeableKg * 2) / 2,
            ShippingPricingConfigService::ROUNDING_NEAREST_KG => round($chargeableKg, 0),
            default => $chargeableKg,
        };
    }
}
