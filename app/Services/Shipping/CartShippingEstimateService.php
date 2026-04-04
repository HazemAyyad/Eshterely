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
     * @param  int|null  $destinationAddressId  User's saved address id; falls back to default address
     * @return array<string, mixed>  Compatible with cart_items.shipping_snapshot
     */
    public function quoteForUser(?User $user, array $validated, int $quantity, ?int $destinationAddressId = null): array
    {
        $meta = $this->resolveDestinationFromUser($user, $destinationAddressId);
        if ($meta === null) {
            return [];
        }
        $destinationCountry = $meta['country'];

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

            $measurementsSource = ($mapped['estimated'] ?? false) ? 'fallback' : 'exact';

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
                'destination_address_id' => $meta['address_id'],
                'destination_label' => $meta['label'],
                'measurements_source' => $measurementsSource,
                'package_weight' => (float) ($input['weight'] ?? 0),
                'package_weight_unit' => $input['weight_unit'] ?? null,
                'package_length' => (float) ($input['length'] ?? 0),
                'package_width' => (float) ($input['width'] ?? 0),
                'package_height' => (float) ($input['height'] ?? 0),
                'package_dimension_unit' => $input['dimension_unit'] ?? null,
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
        $snap = is_array($item->shipping_snapshot) ? $item->shipping_snapshot : [];
        $destAddrId = isset($snap['destination_address_id']) ? (int) $snap['destination_address_id'] : null;
        $destAddrId = $destAddrId > 0 ? $destAddrId : null;

        $quote = $this->quoteForUser($item->user, [
            'weight' => $item->weight,
            'weight_unit' => $item->weight_unit,
            'length' => $item->length,
            'width' => $item->width,
            'height' => $item->height,
            'dimension_unit' => $item->dimension_unit,
        ], (int) $item->quantity, $destAddrId);

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

    /**
     * @return array{country: string, address_id: int, label: string}|null
     */
    private function resolveDestinationFromUser(?User $user, ?int $destinationAddressId): ?array
    {
        if ($user === null || ! method_exists($user, 'addresses')) {
            return null;
        }

        $addr = null;
        if ($destinationAddressId !== null && $destinationAddressId > 0) {
            $addr = $user->addresses()
                ->with(['country', 'city'])
                ->whereKey($destinationAddressId)
                ->first();
        }
        if ($addr === null) {
            $addr = $user->addresses()
                ->where('is_default', true)
                ->with(['country', 'city'])
                ->first();
        }
        if ($addr === null) {
            return null;
        }

        $code = $addr->country?->code ?? null;
        if (! is_string($code) || trim($code) === '') {
            return null;
        }
        $code = Str::upper(trim($code));

        $parts = array_filter([
            $addr->nickname,
            $addr->city?->name,
            $addr->country?->name,
        ], fn ($v) => is_string($v) && trim($v) !== '');
        $label = $parts !== [] ? implode(' · ', $parts) : $code;

        return [
            'country' => $code,
            'address_id' => (int) $addr->id,
            'label' => $label,
        ];
    }
}
