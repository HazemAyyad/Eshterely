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
            'checkout_url' => $this->resource['checkout_url'],
            'status' => $this->resource['status'],
        ];
    }
}
