<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderShipmentEvent extends Model
{
    public const TYPE_CREATED = 'created';
    public const TYPE_PACKED = 'packed';
    public const TYPE_PURCHASED = 'purchased';
    public const TYPE_SHIPPED_TO_WAREHOUSE = 'shipped_to_warehouse';
    public const TYPE_RECEIVED_AT_WAREHOUSE = 'received_at_warehouse';
    public const TYPE_INTERNATIONAL_SHIPPING = 'international_shipping';
    public const TYPE_ARRIVED_DESTINATION_COUNTRY = 'arrived_destination_country';
    public const TYPE_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const TYPE_DELIVERED = 'delivered';
    public const TYPE_EXCEPTION = 'exception';

    protected $fillable = [
        'order_shipment_id',
        'event_type',
        'event_label',
        'event_time',
        'location',
        'payload',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'event_time' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(OrderShipment::class, 'order_shipment_id');
    }

    public static function eventTypes(): array
    {
        return [
            self::TYPE_CREATED,
            self::TYPE_PACKED,
            self::TYPE_PURCHASED,
            self::TYPE_SHIPPED_TO_WAREHOUSE,
            self::TYPE_RECEIVED_AT_WAREHOUSE,
            self::TYPE_INTERNATIONAL_SHIPPING,
            self::TYPE_ARRIVED_DESTINATION_COUNTRY,
            self::TYPE_OUT_FOR_DELIVERY,
            self::TYPE_DELIVERED,
            self::TYPE_EXCEPTION,
        ];
    }
}
