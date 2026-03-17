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

        $productSnapshot = is_array($item->product_snapshot) ? $item->product_snapshot : [];
        $pricingSnapshot = is_array($item->pricing_snapshot) ? $item->pricing_snapshot : null;

        $imageUrl = $item->image_url;
        if (($imageUrl === null || $imageUrl === '') && isset($productSnapshot['image_url'])) {
            $imageUrl = is_string($productSnapshot['image_url']) ? $productSnapshot['image_url'] : $imageUrl;
        }

        return [
            'id' => (string) $item->id,
            'name' => $item->name ?: ($productSnapshot['title'] ?? ''),
            'store_name' => $item->store_name ?: ($productSnapshot['store_name'] ?? null),
            'source_store' => $productSnapshot['store_name'] ?? $productSnapshot['store_key'] ?? $item->store_name,
            'sku' => $item->sku,

            // unit price (preferred) + legacy compatibility
            'unit_price' => (float) $item->price,
            'price' => (float) $item->price,
            'quantity' => $item->quantity,
            'image_url' => $imageUrl,
            'estimated' => (bool) $item->estimated,
            'needs_review' => (bool) ($item->review_metadata['needs_review'] ?? false),
            'missing_fields' => $item->missing_fields ?? [],

            // snapshots for order detail clarity
            'product_snapshot' => $productSnapshot ?: null,
            'pricing_snapshot' => $pricingSnapshot,
        ];
    }
}
