<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckoutSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'payment_id' => $this->resource['payment_id'],
            'reference' => $this->resource['reference'],
            'provider' => $this->resource['provider'] ?? 'square',
            'checkout_url' => $this->resource['checkout_url'],
            'status' => $this->resource['status'],
            'order_id' => isset($this->resource['order_id']) ? (string) $this->resource['order_id'] : null,
        ];
    }
}
