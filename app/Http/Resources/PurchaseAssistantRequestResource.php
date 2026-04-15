<?php

namespace App\Http\Resources;

use App\Models\PurchaseAssistantRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseAssistantRequest
 */
class PurchaseAssistantRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PurchaseAssistantRequest $r */
        $r = $this->resource;

        return [
            'id' => (string) $r->id,
            'source_url' => $r->source_url,
            'source_domain' => $r->source_domain,
            'store_display_name' => $r->store_display_name,
            'title' => $r->title,
            'details' => $r->details,
            'quantity' => $r->quantity,
            'variant_details' => $r->variant_details,
            'customer_estimated_price' => $r->customer_estimated_price !== null ? (float) $r->customer_estimated_price : null,
            'currency' => $r->currency,
            'image_urls' => $r->image_paths ?? [],
            'admin_product_price' => $r->admin_product_price !== null ? (float) $r->admin_product_price : null,
            'admin_service_fee' => $r->admin_service_fee !== null ? (float) $r->admin_service_fee : null,
            'admin_notes' => $r->admin_notes,
            'status' => $r->status,
            'origin' => $r->origin,
            'converted_order_id' => $r->converted_order_id !== null ? (string) $r->converted_order_id : null,
            'created_at' => $r->created_at?->toIso8601String(),
            'updated_at' => $r->updated_at?->toIso8601String(),
        ];
    }
}
