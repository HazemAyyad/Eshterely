<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLineItem extends Model
{
    protected $fillable = ['order_shipment_id', 'name', 'store_name', 'sku', 'price', 'quantity', 'image_url', 'badges', 'weight_kg', 'dimensions', 'shipping_method'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity' => 'integer',
            'weight_kg' => 'decimal:4',
            'badges' => 'array',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(OrderShipment::class, 'order_shipment_id');
    }
}
