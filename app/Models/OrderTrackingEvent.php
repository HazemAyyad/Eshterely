<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTrackingEvent extends Model
{
    protected $fillable = ['order_shipment_id', 'title', 'subtitle', 'icon', 'is_highlighted', 'sort_order'];

    protected function casts(): array
    {
        return ['is_highlighted' => 'boolean'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(OrderShipment::class, 'order_shipment_id');
    }
}
