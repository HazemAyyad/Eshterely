<?php

namespace App\Services\Shipping;

/**
 * Maps normalized product preview/extraction data to ShippingQuoteService input.
 * Source-agnostic: works with output from ScraperAPI, HTML pipeline, or any extractor
 * that populates the standard normalized fields.
 */
class ProductToShippingInputMapper
{
    /** Default weight (kg) when product weight is missing. */
    private const FALLBACK_WEIGHT_KG = 0.5;

    /** Default dimensions (cm) when product dimensions are missing. */
    private const FALLBACK_LENGTH_CM = 10.0;

    private const FALLBACK_WIDTH_CM = 10.0;

    private const FALLBACK_HEIGHT_CM = 10.0;

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
            $weight = self::FALLBACK_WEIGHT_KG;
            $weightUnit = PackageNormalizer::WEIGHT_UNIT_KG;
        }
        $hasAnyDimension = $length > 0 || $width > 0 || $height > 0;
        if (! $hasAnyDimension) {
            $missing[] = 'length';
            $missing[] = 'width';
            $missing[] = 'height';
            $length = self::FALLBACK_LENGTH_CM;
            $width = self::FALLBACK_WIDTH_CM;
            $height = self::FALLBACK_HEIGHT_CM;
            $dimensionUnit = PackageNormalizer::DIMENSION_UNIT_CM;
        } else {
            if ($length <= 0) {
                $missing[] = 'length';
                $length = self::FALLBACK_LENGTH_CM;
            }
            if ($width <= 0) {
                $missing[] = 'width';
                $width = self::FALLBACK_WIDTH_CM;
            }
            if ($height <= 0) {
                $missing[] = 'height';
                $height = self::FALLBACK_HEIGHT_CM;
            }
        }

        $destinationCountry = (string) ($overrides['destination_country'] ?? $normalizedProduct['destination_country'] ?? config('services.shipping.default_destination_country', 'US'));
        $destinationCountry = strtoupper(substr(trim($destinationCountry), 0, 10)) ?: 'US';
        $warehouseMode = (bool) ($overrides['warehouse_mode'] ?? $normalizedProduct['warehouse_mode'] ?? false);

        $input = [
            'destination_country' => $destinationCountry,
            'warehouse_mode' => $warehouseMode,
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
        $u = strtolower(trim((string) $u));

        return $u === 'lb' || $u === 'lbs' ? PackageNormalizer::WEIGHT_UNIT_LB : PackageNormalizer::WEIGHT_UNIT_KG;
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
