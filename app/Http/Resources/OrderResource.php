<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;

        return [
            'id' => (string) $order->id,
            'status' => $order->status,
            'total' => (float) $order->total_amount,
            'currency' => $order->currency,
            'estimated' => (bool) $order->estimated,
            'needs_review' => (bool) $order->needs_review,
            'order_number' => $order->order_number,
            'items' => $this->whenLoaded('shipments', function () use ($order) {
                $lineItems = $order->shipments->flatMap(fn ($s) => $s->lineItems);
                return OrderItemResource::collection($lineItems);
            }),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
