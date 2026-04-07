<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderLineItem extends Model
{
    public const FULFILLMENT_REVIEWED = 'reviewed';

    public const FULFILLMENT_PAID = 'paid';

    public const FULFILLMENT_PURCHASED = 'purchased';

    public const FULFILLMENT_IN_TRANSIT_TO_WAREHOUSE = 'in_transit_to_warehouse';

    public const FULFILLMENT_ARRIVED_AT_WAREHOUSE = 'arrived_at_warehouse';

    public const FULFILLMENT_READY_FOR_SHIPMENT = 'ready_for_shipment';

    protected $fillable = [
        'order_shipment_id', 'draft_order_item_id', 'source_type', 'cart_item_id', 'imported_product_id',
        'name', 'store_name', 'sku', 'price', 'quantity', 'fulfillment_status', 'image_url', 'badges', 'weight_kg', 'dimensions', 'shipping_method',
        'product_snapshot', 'pricing_snapshot', 'review_metadata', 'estimated', 'missing_fields',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity' => 'integer',
            'weight_kg' => 'decimal:4',
            'badges' => 'array',
            'product_snapshot' => 'array',
            'pricing_snapshot' => 'array',
            'review_metadata' => 'array',
            'estimated' => 'boolean',
            'missing_fields' => 'array',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(OrderShipment::class, 'order_shipment_id');
    }

    public function draftOrderItem(): BelongsTo
    {
        return $this->belongsTo(DraftOrderItem::class, 'draft_order_item_id');
    }

    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(CartItem::class);
    }

    public function importedProduct(): BelongsTo
    {
        return $this->belongsTo(ImportedProduct::class);
    }

    public function warehouseReceipts(): HasMany
    {
        return $this->hasMany(WarehouseReceipt::class);
    }

    public function shipmentItems(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function latestWarehouseReceipt(): HasOne
    {
        return $this->hasOne(WarehouseReceipt::class)->latestOfMany('received_at');
    }
}
