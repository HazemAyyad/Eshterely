<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLineItem extends Model
{
    protected $fillable = [
        'order_shipment_id', 'draft_order_item_id', 'source_type', 'cart_item_id', 'imported_product_id',
        'name', 'store_name', 'sku', 'price', 'quantity', 'image_url', 'badges', 'weight_kg', 'dimensions', 'shipping_method',
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
}
