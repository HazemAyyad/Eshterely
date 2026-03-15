<?php

namespace App\Http\Resources;

use App\Models\DraftOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DraftOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DraftOrderItem $item */
        $item = $this->resource;

        return [
            'id' => (string) $item->id,
            'cart_item_id' => $item->cart_item_id ? (string) $item->cart_item_id : null,
            'imported_product_id' => $item->imported_product_id ? (string) $item->imported_product_id : null,
            'product_snapshot' => $item->product_snapshot,
            'shipping_snapshot' => $item->shipping_snapshot,
            'pricing_snapshot' => $item->pricing_snapshot,
            'quantity' => $item->quantity,
            'review_metadata' => $item->review_metadata,
            'estimated' => (bool) $item->estimated,
            'missing_fields' => $item->missing_fields ?? [],
        ];
    }
}
