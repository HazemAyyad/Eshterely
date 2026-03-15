<?php

namespace App\Http\Resources;

use App\Models\OrderLineItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrderLineItem $item */
        $item = $this->resource;

        return [
            'id' => (string) $item->id,
            'name' => $item->name,
            'store_name' => $item->store_name,
            'sku' => $item->sku,
            'price' => (float) $item->price,
            'quantity' => $item->quantity,
            'image_url' => $item->image_url,
            'estimated' => (bool) $item->estimated,
            'needs_review' => (bool) ($item->review_metadata['needs_review'] ?? false),
        ];
    }
}
