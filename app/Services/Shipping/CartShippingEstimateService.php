<?php

namespace App\Services\Shipping;

use App\Models\CartItem;
use App\Models\User;
use App\Services\CartItemReviewService;
use Illuminate\Support\Str;

class CartShippingEstimateService
{
    public function __construct(
        private ProductToShippingInputMapper $shippingInputMapper,
        private ShippingQuoteService $shippingQuoteService,
        private CartItemReviewService $reviewService,
    ) {}

    /**
     * @param  array<string, mixed>  $validated  Cart line fields (weight, dimensions, etc.)
     * @return array<string, mixed>  Compatible with cart_items.shipping_snapshot
     */
    public function quoteForUser(?User $user, array $validated, int $quantity): array
    {
        $destinationCountry = $this->resolveDestinationCountry($user);
        if (! is_string($destinationCountry) || $destinationCountry === '') {
            return [];
        }

        $normalized = [
            'weight' => $validated['weight'] ?? null,
            'weight_unit' => $validated['weight_unit'] ?? null,
            'length' => $validated['length'] ?? null,
            'width' => $validated['width'] ?? null,
            'height' => $validated['height'] ?? null,
            'dimension_unit' => $validated['dimension_unit'] ?? null,
            'quantity' => $quantity,
        ];

        $overrides = [
            'quantity' => $quantity,
            'destination_country' => $destinationCountry,
        ];

        try {
            $mapped = $this->shippingInputMapper->fromNormalizedProduct($normalized, $overrides);
            $input = $mapped['input'] ?? null;
            if (! is_array($input)) {
                return [];
            }

            $result = $this->shippingQuoteService->quote($input);

            return [
                'carrier' => $result->carrier ?? 'auto',
                'pricing_mode' => $result->pricingMode ?? 'default',
                'warehouse_mode' => (bool) ($result->warehouseMode ?? false),
                'actual_weight' => (float) ($result->actualWeightKg ?? 0),
                'volumetric_weight' => (float) ($result->volumetricWeightKg ?? 0),
                'chargeable_weight' => (float) ($result->chargeableWeightKg ?? 0),
                'currency' => (string) ($result->currency ?? 'USD'),
                'amount' => (float) ($result->finalAmount ?? 0),
                'estimated' => (bool) ($mapped['estimated'] ?? false),
                'missing_fields' => $mapped['missing_fields'] ?? [],
                'notes' => $result->calculationNotes ?? [],
                'calculation_breakdown' => $result->calculationBreakdown ?? [],
                'destination_country' => $destinationCountry,
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Re-run the shipping engine from stored cart line data and persist snapshot + flags.
     */
    public function recalculateAndPersist(CartItem $item): CartItem
    {
        $item->loadMissing('user');
        $quote = $this->quoteForUser($item->user, [
            'weight' => $item->weight,
            'weight_unit' => $item->weight_unit,
            'length' => $item->length,
            'width' => $item->width,
            'height' => $item->height,
            'dimension_unit' => $item->dimension_unit,
        ], (int) $item->quantity);

        if ($quote === []) {
            return $item;
        }

        $item->update([
            'shipping_cost' => $quote['amount'] ?? null,
            'shipping_snapshot' => $quote,
            'estimated' => (bool) ($quote['estimated'] ?? false),
            'missing_fields' => $quote['missing_fields'] ?? [],
            'carrier' => $quote['carrier'] ?? null,
            'pricing_mode' => $quote['pricing_mode'] ?? null,
            'needs_review' => $this->reviewService->snapshotNeedsReview(
                (bool) ($quote['estimated'] ?? false),
                $quote['missing_fields'] ?? [],
                $quote['carrier'] ?? null,
                $quote,
                null
            ),
        ]);

        return $item->fresh();
    }

    private function resolveDestinationCountry(?User $user): ?string
    {
        $default = null;
        if ($user && method_exists($user, 'addresses')) {
            $default = $user->addresses()->where('is_default', true)->with('country')->first();
        }
        $destinationCountry = $default?->country?->code ?? null;
        if (is_string($destinationCountry)) {
            $destinationCountry = Str::upper(trim($destinationCountry));
        }
        if (is_string($destinationCountry) && $destinationCountry !== '') {
            return $destinationCountry;
        }

        return null;
    }
}
