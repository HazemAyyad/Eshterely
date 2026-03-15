<?php

namespace App\Http\Resources;

use App\Models\DraftOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DraftOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DraftOrder $draft */
        $draft = $this->resource;

        return [
            'id' => (string) $draft->id,
            'status' => $draft->status,
            'currency' => $draft->currency,
            'subtotal' => (float) $draft->subtotal_snapshot,
            'shipping_total' => (float) $draft->shipping_total_snapshot,
            'service_fee_total' => (float) $draft->service_fee_total_snapshot,
            'final_total' => (float) $draft->final_total_snapshot,
            'estimated' => (bool) $draft->estimated,
            'needs_review' => (bool) $draft->needs_review,
            'review_state' => $draft->review_state,
            'notes' => $draft->notes,
            'warnings' => $draft->warnings,
            'items' => $this->whenLoaded('items', fn () => DraftOrderItemResource::collection($this->resource->items)),
            'created_at' => $draft->created_at?->toIso8601String(),
            'updated_at' => $draft->updated_at?->toIso8601String(),
        ];
    }
}
