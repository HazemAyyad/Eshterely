<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportedProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $quantity = (int) (($this->package_info['quantity'] ?? 1) ?: 1);
        $quantity = $quantity < 1 ? 1 : $quantity;

        $unitPrice = (float) $this->product_price;
        $subtotal = round($unitPrice * $quantity, 2);
        $shipping = is_array($this->shipping_quote_snapshot) && isset($this->shipping_quote_snapshot['amount'])
            ? (float) $this->shipping_quote_snapshot['amount']
            : 0.0;
        $shippingEstimated = (bool) ($this->shipping_quote_snapshot['estimated'] ?? $this->final_pricing_snapshot['estimated'] ?? false);
        $needsReview = $shippingEstimated || ! empty($this->missing_fields);

        $final = is_array($this->final_pricing_snapshot) && isset($this->final_pricing_snapshot['final_total'])
            ? (float) $this->final_pricing_snapshot['final_total']
            : round($subtotal + $shipping, 2);

        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'image_url' => $this->image_url,
            'source_url' => $this->source_url,
            'product_price' => (float) $this->product_price,
            'product_currency' => $this->product_currency,
            'shipping_quote' => $this->shipping_quote_snapshot,
            'final_pricing' => $this->final_pricing_snapshot,
            'estimated' => (bool) $this->estimated,
            'missing_fields' => $this->missing_fields,
            'status' => $this->status,
            'carrier' => $this->carrier,
            'pricing_mode' => $this->pricing_mode,
            'quantity' => $quantity,

            // Explicit pricing contract for confirm/import screens
            'pricing' => [
                'currency' => $this->product_currency ?: 'USD',
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                'shipping_amount' => round($shipping, 2),
                'shipping_estimated' => $shippingEstimated,
                'needs_review' => $needsReview,
                'total' => round($final, 2),
                'breakdown' => array_values(array_filter([
                    ['key' => 'product', 'label' => 'Product', 'amount' => $subtotal],
                    ['key' => 'shipping', 'label' => 'Shipping', 'amount' => round($shipping, 2), 'estimated' => $shippingEstimated],
                    is_array($this->final_pricing_snapshot) && isset($this->final_pricing_snapshot['service_fee'])
                        ? ['key' => 'service_fee', 'label' => 'Service fee', 'amount' => (float) $this->final_pricing_snapshot['service_fee']]
                        : null,
                    is_array($this->final_pricing_snapshot) && isset($this->final_pricing_snapshot['markup_amount'])
                        ? ['key' => 'markup', 'label' => 'Markup', 'amount' => (float) $this->final_pricing_snapshot['markup_amount']]
                        : null,
                ])),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
