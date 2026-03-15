<?php

namespace App\Services\Shipping;

/**
 * Maps normalized product preview/extraction data to ShippingQuoteService input.
 * Source-agnostic: works with output from ScraperAPI, HTML pipeline, or any extractor
 * that populates the standard normalized fields.
 * Fallback package values (weight, dimensions) are loaded from ShippingPricingConfigService
 * when product data does not contain valid values.
 */
class ProductToShippingInputMapper
{
    public function __construct(
        private ShippingPricingConfigService $config,
        private PackageNormalizer $normalizer
    ) {}

    /**
     * Build shipping quote input from normalized product data.
     * Uses fallbacks for missing weight/dimensions and tracks what was missing.
     *
     * @param  array<string, mixed>  $normalizedProduct  Product data from extraction pipeline (any source)
     * @param  array{destination_country?: string, warehouse_mode?: bool, quantity?: int}  $overrides  Optional overrides (e.g. from request)
     * @return array{input: array<string, mixed>, missing_fields: array<int, string>, estimated: bool}
     */
    public function fromNormalizedProduct(array $normalizedProduct, array $overrides = []): array
    {
        $missing = [];
        $weight = $this->extractWeight($normalizedProduct);
        $weightUnit = $this->extractWeightUnit($normalizedProduct);
        $length = $this->extractDimension($normalizedProduct, 'length');
        $width = $this->extractDimension($normalizedProduct, 'width');
        $height = $this->extractDimension($normalizedProduct, 'height');
        $dimensionUnit = $this->extractDimensionUnit($normalizedProduct);
        $quantity = (int) ($overrides['quantity'] ?? $normalizedProduct['quantity'] ?? 1);
        $quantity = $quantity < 1 ? 1 : $quantity;

        if ($weight <= 0) {
            $missing[] = 'weight';
            $weight = $this->config->shippingDefaultWeight();
            $weightUnit = $this->config->shippingDefaultWeightUnit() === 'lb' ? PackageNormalizer::WEIGHT_UNIT_LB : PackageNormalizer::WEIGHT_UNIT_KG;
        }
        $hasAnyDimension = $length > 0 || $width > 0 || $height > 0;
        $dimUnit = $this->config->shippingDefaultDimensionUnit();
        $defaultDimUnit = $dimUnit === 'in' ? PackageNormalizer::DIMENSION_UNIT_IN : PackageNormalizer::DIMENSION_UNIT_CM;
        if (! $hasAnyDimension) {
            $missing[] = 'length';
            $missing[] = 'width';
            $missing[] = 'height';
            $length = $this->config->shippingDefaultLength();
            $width = $this->config->shippingDefaultWidth();
            $height = $this->config->shippingDefaultHeight();
            $dimensionUnit = $defaultDimUnit;
        } else {
            if ($length <= 0) {
                $missing[] = 'length';
                $length = $this->config->shippingDefaultLength();
            }
            if ($width <= 0) {
                $missing[] = 'width';
                $width = $this->config->shippingDefaultWidth();
            }
            if ($height <= 0) {
                $missing[] = 'height';
                $height = $this->config->shippingDefaultHeight();
            }
        }

        $destinationCountry = (string) ($overrides['destination_country'] ?? $normalizedProduct['destination_country'] ?? config('services.shipping.default_destination_country', 'US'));
        $destinationCountry = strtoupper(substr(trim($destinationCountry), 0, 10)) ?: 'US';
        $warehouseMode = (bool) ($overrides['warehouse_mode'] ?? $normalizedProduct['warehouse_mode'] ?? false);
        $carrier = isset($overrides['carrier']) && $overrides['carrier'] !== '' && $overrides['carrier'] !== null
            ? (string) $overrides['carrier']
            : null;

        $input = [
            'destination_country' => $destinationCountry,
            'warehouse_mode' => $warehouseMode,
            'carrier' => $carrier,
            'weight' => $weight,
            'weight_unit' => $weightUnit,
            'length' => $length,
            'width' => $width,
            'height' => $height,
            'dimension_unit' => $dimensionUnit,
            'quantity' => $quantity,
        ];

        $estimated = $missing !== [];

        return [
            'input' => $input,
            'missing_fields' => array_values(array_unique($missing)),
            'estimated' => $estimated,
        ];
    }

    /**
     * Return whether we have enough data for a meaningful quote.
     * We require at least weight > 0 OR (length > 0 and width > 0 and height > 0).
     *
     * @param  array<string, mixed>  $normalizedProduct
     */
    public function hasEnoughDataForQuote(array $normalizedProduct): bool
    {
        $weight = $this->extractWeight($normalizedProduct);
        $length = $this->extractDimension($normalizedProduct, 'length');
        $width = $this->extractDimension($normalizedProduct, 'width');
        $height = $this->extractDimension($normalizedProduct, 'height');

        if ($weight > 0) {
            return true;
        }

        return $length > 0 && $width > 0 && $height > 0;
    }

    private function extractWeight(array $data): float
    {
        $v = $data['weight'] ?? $data['weight_kg'] ?? null;
        if ($v === null || $v === '') {
            return 0.0;
        }

        return (float) $v;
    }

    private function extractWeightUnit(array $data): string
    {
        $u = $data['weight_unit'] ?? null;
        if ($u === null || $u === '') {
            return PackageNormalizer::WEIGHT_UNIT_KG;
        }
        return $this->normalizer->normalizeWeightUnit($u);
    }

    private function extractDimension(array $data, string $key): float
    {
        $v = $data[$key] ?? null;
        if ($v === null || $v === '') {
            return 0.0;
        }

        return (float) $v;
    }

    private function extractDimensionUnit(array $data): string
    {
        $u = $data['dimension_unit'] ?? $data['dimensions_unit'] ?? null;
        if ($u === null || $u === '') {
            return PackageNormalizer::DIMENSION_UNIT_CM;
        }
        $u = strtolower(trim((string) $u));

        return $u === 'in' || $u === 'inch' || $u === 'inches' ? PackageNormalizer::DIMENSION_UNIT_IN : PackageNormalizer::DIMENSION_UNIT_CM;
    }
}
