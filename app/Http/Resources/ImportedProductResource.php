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
        return [
            'id' => $this->id,
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
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
