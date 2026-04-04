<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Log;

/**
 * Integrates shipping quote calculation into the product import flow.
 * Uses normalized product data (source-agnostic) and ShippingQuoteService.
 * On failure or insufficient data, returns null without breaking the import.
 */
class ProductImportShippingQuoteService
{
    public function __construct(
        private ProductToShippingInputMapper $mapper,
        private ShippingQuoteService $quoteService
    ) {}

    /**
     * Build a shipping quote from normalized product data.
     * Returns null if data is insufficient or quote calculation fails.
     *
     * @param  array<string, mixed>  $normalizedProduct  Product data from extraction pipeline
     * @param  array{destination_country?: string, warehouse_mode?: bool, quantity?: int}  $overrides  Optional request/config overrides
     * @param  string|null  $extractionSource  Optional extraction source for logging
     * @return array{carrier: string|null, warehouse_mode: bool, actual_weight: float, volumetric_weight: float, chargeable_weight: float, currency: string, amount: float, estimated: bool, missing_fields: array, notes: array}|null
     */
    public function quoteFromProduct(array $normalizedProduct, array $overrides = [], ?string $extractionSource = null): ?array
    {
        if (! $this->mapper->hasEnoughDataForQuote($normalizedProduct)) {
            Log::info('Product import shipping: skipping quote – insufficient weight and dimensions', [
                'extraction_source' => $extractionSource,
            ]);

            return null;
        }

        $mapped = $this->mapper->fromNormalizedProduct($normalizedProduct, $overrides);
        $input = $mapped['input'];
        $missingFields = $mapped['missing_fields'];
        $estimated = $mapped['estimated'];

        if ($estimated && $missingFields !== []) {
            Log::info('Product import shipping: using fallback defaults for missing fields', [
                'missing_fields' => $missingFields,
                'extraction_source' => $extractionSource,
            ]);
        }

        try {
            $result = $this->quoteService->quote($input);
        } catch (\Throwable $e) {
            Log::warning('Product import shipping: quote calculation failed', [
                'message' => $e->getMessage(),
                'extraction_source' => $extractionSource,
            ]);

            return null;
        }

        $notes = $result->calculationNotes;
        if ($estimated && $missingFields !== []) {
            $notes[] = 'Quote is estimated: missing or incomplete ' . implode(', ', $missingFields);
        }

        $measurementsSource = $estimated ? 'fallback' : 'exact';

        return [
            'carrier' => $result->carrier ?? 'auto',
            'pricing_mode' => $result->pricingMode ?? 'default',
            'warehouse_mode' => $result->warehouseMode,
            'actual_weight' => $result->actualWeightKg,
            'volumetric_weight' => $result->volumetricWeightKg,
            'chargeable_weight' => $result->chargeableWeightKg,
            'currency' => $result->currency,
            'amount' => $result->finalAmount,
            'estimated' => $estimated,
            'missing_fields' => $missingFields,
            'notes' => $notes,
            'calculation_breakdown' => $result->calculationBreakdown ?? [],
            'measurements_source' => $measurementsSource,
            'package_weight' => (float) ($input['weight'] ?? 0),
            'package_weight_unit' => $input['weight_unit'] ?? null,
            'package_length' => (float) ($input['length'] ?? 0),
            'package_width' => (float) ($input['width'] ?? 0),
            'package_height' => (float) ($input['height'] ?? 0),
            'package_dimension_unit' => $input['dimension_unit'] ?? null,
        ];
    }
}
