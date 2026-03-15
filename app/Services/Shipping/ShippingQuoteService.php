<?php

namespace App\Services\Shipping;

use App\Services\Shipping\Contracts\CarrierPricingResolverInterface;

/**
 * Main entry for shipping quotes.
 * Supports carrier = dhl | ups | fedex | auto. Uses carrier resolvers and config for all parameters.
 */
class ShippingQuoteService
{
    public function __construct(
        private PackageNormalizer $normalizer,
        private VolumetricWeightCalculator $volumetricCalculator,
        private ShippingPricingConfigService $config,
        private CarrierPricingResolverRegistry $resolverRegistry
    ) {}

    /**
     * Build a quote from raw input.
     * carrier: dhl, ups, fedex, or auto (evaluate all and pick best).
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

        $carrierKey = $this->normalizeCarrierInput($package->carrier);
        $context = new CarrierPricingContext(
            carrier: $carrierKey,
            destinationCountry: $package->destinationCountry ?: 'US',
            warehouseMode: $package->warehouseMode,
            actualWeightKg: $package->weightKg,
            volumetricWeightKg: $weights['volumetric_kg'],
            chargeableWeightKg: $weights['chargeable_kg'],
            lengthCm: $package->lengthCm,
            widthCm: $package->widthCm,
            heightCm: $package->heightCm,
            quantity: $package->quantity,
        );

        $carrierResults = [];
        $selectedResult = null;

        if ($carrierKey === 'auto') {
            $results = [];
            foreach ($this->resolverRegistry->all() as $resolver) {
                $ctx = new CarrierPricingContext(
                    carrier: $this->resolverCarrierKey($resolver),
                    destinationCountry: $context->destinationCountry,
                    warehouseMode: $context->warehouseMode,
                    actualWeightKg: $context->actualWeightKg,
                    volumetricWeightKg: $context->volumetricWeightKg,
                    chargeableWeightKg: $context->chargeableWeightKg,
                    lengthCm: $context->lengthCm,
                    widthCm: $context->widthCm,
                    heightCm: $context->heightCm,
                    quantity: $context->quantity,
                );
                $res = $resolver->resolve($ctx);
                $results[] = $res;
                $carrierResults[] = new CarrierQuoteResult(
                    carrier: $res->carrier,
                    currency: $res->currency,
                    amount: $res->amount,
                    notes: $res->notes,
                    pricingMode: $res->pricingMode,
                    breakdown: $res->breakdown,
                );
            }
            $selectedResult = $this->selectBestResult($results);
            if ($selectedResult === null && $carrierResults !== []) {
                $selectedResult = $this->carrierResultToPricingResult($carrierResults[0]);
            }
        } else {
            $resolver = $this->resolverRegistry->get($carrierKey);
            if ($resolver !== null) {
                $selectedResult = $resolver->resolve($context);
                $carrierResults[] = new CarrierQuoteResult(
                    carrier: $selectedResult->carrier,
                    currency: $selectedResult->currency,
                    amount: $selectedResult->amount,
                    notes: $selectedResult->notes,
                    pricingMode: $selectedResult->pricingMode,
                    breakdown: $selectedResult->breakdown,
                );
            }
        }

        if ($selectedResult === null) {
            $selectedResult = $this->fallbackQuote($context, $volumetricKg, $chargeableKg, $totalWeightKg);
            $carrierSlug = $carrierKey === 'auto' ? ($carrierResults[0]->carrier ?? 'default') : $carrierKey;
            if ($carrierResults === []) {
                $carrierResults[] = new CarrierQuoteResult(
                    carrier: $carrierSlug,
                    currency: $selectedResult->currency,
                    amount: $selectedResult->amount,
                    notes: $selectedResult->notes,
                    pricingMode: $selectedResult->pricingMode,
                    breakdown: $selectedResult->breakdown,
                );
            }
        }

        $configSnapshot = $this->config->snapshotForQuote();

        return new ShippingQuoteResult(
            carrier: $selectedResult->carrier,
            warehouseMode: $package->warehouseMode,
            actualWeightKg: $totalWeightKg,
            volumetricWeightKg: $volumetricKg,
            chargeableWeightKg: $chargeableKg,
            currency: $selectedResult->currency,
            finalAmount: $selectedResult->amount,
            calculationNotes: $selectedResult->notes,
            appliedConfigSnapshot: $configSnapshot,
            carrierResults: $carrierResults,
            pricingMode: $selectedResult->pricingMode,
            calculationBreakdown: $selectedResult->breakdown,
        );
    }

    private function normalizeCarrierInput(?string $carrier): string
    {
        if ($carrier === null || $carrier === '') {
            return 'auto';
        }
        $c = strtolower(trim($carrier));

        return in_array($c, ['dhl', 'ups', 'fedex', 'auto'], true) ? $c : 'auto';
    }

    private function resolverCarrierKey(CarrierPricingResolverInterface $resolver): string
    {
        foreach (['dhl', 'ups', 'fedex'] as $key) {
            if ($resolver->supportsCarrier($key)) {
                return $key;
            }
        }

        return 'default';
    }

    /**
     * @param  list<CarrierPricingResult>  $results
     */
    private function selectBestResult(array $results): ?CarrierPricingResult
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

    private function carrierResultToPricingResult(CarrierQuoteResult $r): CarrierPricingResult
    {
        return new CarrierPricingResult(
            carrier: $r->carrier,
            currency: $r->currency,
            amount: $r->amount,
            pricingMode: $r->pricingMode,
            breakdown: $r->breakdown,
            notes: $r->notes,
        );
    }

    private function fallbackQuote(CarrierPricingContext $context, float $volumetricKg, float $chargeableKg, float $totalWeightKg): CarrierPricingResult
    {
        $currency = $this->config->defaultCurrency();
        $minCharge = $this->config->minShippingCharge();
        $warehouseFee = $context->warehouseMode ? $this->config->warehouseHandlingFee() : 0.0;
        $multiPackagePercent = $context->quantity > 1 ? $this->config->multiPackagePercent() : 0.0;
        $markup = $this->config->defaultMarkupPercent();

        $baseAmount = $minCharge + $warehouseFee;
        $amountAfterMarkup = $baseAmount * (1 + $markup / 100);
        $amountAfterMultiPackage = $multiPackagePercent > 0
            ? $amountAfterMarkup * (1 + $multiPackagePercent / 100)
            : $amountAfterMarkup;
        $finalAmount = max($minCharge, round($amountAfterMultiPackage, 2));

        $notes = [];
        $notes[] = sprintf('Actual weight: %.3f kg', $totalWeightKg);
        $notes[] = sprintf('Volumetric weight: %.3f kg', $volumetricKg);
        $notes[] = sprintf('Chargeable weight: %.3f kg', $chargeableKg);
        if ($context->warehouseMode && $warehouseFee > 0) {
            $notes[] = sprintf('Warehouse handling fee: %s %.2f', $currency, $warehouseFee);
        }
        if ($context->quantity > 1 && $multiPackagePercent > 0) {
            $notes[] = sprintf('Multi-package adjustment: +%s%%', (string) $multiPackagePercent);
        }

        return new CarrierPricingResult(
            carrier: $context->carrier === 'auto' ? 'default' : $context->carrier,
            currency: $currency,
            amount: $finalAmount,
            pricingMode: 'default',
            breakdown: [
                'min_charge' => $minCharge,
                'warehouse_fee' => $warehouseFee,
                'markup_percent' => $markup,
                'multi_package_percent' => $multiPackagePercent,
            ],
            notes: $notes,
        );
    }
}
