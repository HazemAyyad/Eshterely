<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderShipment extends Model
{
    protected $fillable = [
        'order_id', 'country_code', 'country_label', 'shipping_method', 'eta',
        'subtotal', 'shipping_fee', 'customs_duties', 'gross_weight_kg',
        'dimensions', 'insurance_confirmed', 'status_tags', 'shipping_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'customs_duties' => 'decimal:2',
            'gross_weight_kg' => 'decimal:4',
            'status_tags' => 'array',
            'shipping_snapshot' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(OrderLineItem::class, 'order_shipment_id');
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(OrderTrackingEvent::class, 'order_shipment_id');
    }
}
