<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Backward compatible: existing fields unchanged; adds review metadata for imported items.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CartItem $item */
        $item = $this->resource;

        $data = [
            'id' => (string) $item->id,
            'url' => $item->product_url,
            'name' => $item->name,
            'price' => (float) $item->unit_price,
            'quantity' => $item->quantity,
            'currency' => $item->currency,
            'image_url' => $item->image_url,
            'store_key' => $item->store_key,
            'store_name' => $item->store_name,
            'product_id' => $item->product_id,
            'country' => $item->country,
            'source' => $item->source ?? 'paste_link',
            'imported_product_id' => $item->imported_product_id,
            'review_status' => $item->review_status ?? CartItem::REVIEW_STATUS_PENDING,
            'shipping_cost' => $item->shipping_cost ? (float) $item->shipping_cost : null,
            'pricing_snapshot' => $item->pricing_snapshot,
            'shipping_snapshot' => $item->shipping_snapshot,
            'variation_text' => $item->variation_text,
            'weight' => $item->weight ? (float) $item->weight : null,
            'weight_unit' => $item->weight_unit,
            'length' => $item->length ? (float) $item->length : null,
            'width' => $item->width ? (float) $item->width : null,
            'height' => $item->height ? (float) $item->height : null,
            'dimension_unit' => $item->dimension_unit,
        ];

        $data['source_type'] = $item->source_type;
        $data['needs_review'] = (bool) $item->needs_review;
        $data['estimated'] = (bool) $item->estimated;
        $data['missing_fields'] = $item->missing_fields ?? [];
        $data['carrier'] = $item->carrier;
        $data['pricing_mode'] = $item->pricing_mode;
        $data['source_url'] = $item->product_url;
        $data['source_store'] = $item->store_name ?? $item->store_key;

        $snap = is_array($item->shipping_snapshot) ? $item->shipping_snapshot : [];
        $data['shipping_destination'] = [
            'address_id' => $snap['destination_address_id'] ?? null,
            'country_code' => $snap['destination_country'] ?? null,
            'label' => $snap['destination_label'] ?? null,
        ];

        // Pay-now breakdown (product + app fee only; shipping is display-only).
        $data['line_subtotal'] = $item->lineSubtotal();
        $data['app_fee_percent'] = $item->resolvedAppFeePercent();
        $data['app_fee_amount'] = $item->appFeeAmount();
        $data['shipping_estimate_amount'] = $item->shippingEstimateLineAmount();
        $data['shipping_payable_now'] = 0;
        $data['payable_now_total'] = $item->payableNowTotal();

        return $data;
    }
}
