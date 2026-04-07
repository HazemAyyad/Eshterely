<?php

namespace App\Services\Shipments;

use App\Models\Address;
use App\Models\OrderLineItem;
use App\Services\Shipping\ShippingQuoteService;
use Illuminate\Support\Collection;

/**
 * Builds a single-package quote for outbound shipment from warehouse line items + destination.
 */
class ShipmentShippingQuoteBuilder
{
    public function __construct(
        private ShippingQuoteService $quoteService
    ) {}

    /**
     * @param  Collection<int, OrderLineItem>  $lineItems
     * @return array{shipping_cost: float, quote: \App\Services\Shipping\ShippingQuoteResult, total_weight_kg: float, additional_fees: float}
     */
    public function build(Collection $lineItems, Address $address): array
    {
        $lineItems->loadMissing(['latestWarehouseReceipt']);

        $totalWeightKg = 0.0;
        $maxL = 10.0;
        $maxW = 10.0;
        $maxH = 10.0;
        $additionalFees = 0.0;

        foreach ($lineItems as $line) {
            $r = $line->latestWarehouseReceipt;
            $w = (float) ($r?->received_weight ?? $line->weight_kg ?? 0.5);
            if ($w <= 0) {
                $w = 0.5;
            }
            $totalWeightKg += $w * max(1, (int) $line->quantity);

            $l = (float) ($r?->received_length ?? 30);
            $wi = (float) ($r?->received_width ?? 20);
            $h = (float) ($r?->received_height ?? 15);
            if ($l > 0) {
                $maxL = max($maxL, $l);
            }
            if ($wi > 0) {
                $maxW = max($maxW, $wi);
            }
            if ($h > 0) {
                $maxH = max($maxH, $h);
            }

            $additionalFees += round((float) ($r?->additional_fee_amount ?? 0), 2);
        }

        if ($totalWeightKg <= 0) {
            $totalWeightKg = 0.5;
        }

        $country = strtoupper((string) ($address->country?->code ?? 'US'));

        $input = [
            'destination_country' => strlen($country) === 2 ? $country : 'US',
            'carrier' => 'auto',
            'warehouse_mode' => true,
            'weight' => $totalWeightKg,
            'weight_unit' => 'kg',
            'length' => $maxL,
            'width' => $maxW,
            'height' => $maxH,
            'dimension_unit' => 'cm',
            'quantity' => 1,
        ];

        $quote = $this->quoteService->quote($input);
        $shippingCost = round((float) $quote->finalAmount, 2);

        return [
            'shipping_cost' => $shippingCost,
            'quote' => $quote,
            'total_weight_kg' => round($totalWeightKg, 4),
            'additional_fees' => round($additionalFees, 2),
        ];
    }
}
