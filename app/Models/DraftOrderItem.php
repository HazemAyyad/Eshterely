<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DraftOrderItem extends Model
{
    protected $fillable = [
        'draft_order_id',
        'cart_item_id',
        'imported_product_id',
        'source_type',
        'product_snapshot',
        'shipping_snapshot',
        'pricing_snapshot',
        'quantity',
        'review_metadata',
        'estimated',
        'missing_fields',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'product_snapshot' => 'array',
            'shipping_snapshot' => 'array',
            'pricing_snapshot' => 'array',
            'review_metadata' => 'array',
            'estimated' => 'boolean',
            'missing_fields' => 'array',
        ];
    }

    public function draftOrder(): BelongsTo
    {
        return $this->belongsTo(DraftOrder::class);
    }

    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(CartItem::class);
    }

    public function importedProduct(): BelongsTo
    {
        return $this->belongsTo(ImportedProduct::class);
    }

    /** Order line item created from this draft item (for cart restoration tracing). */
    public function orderLineItem(): HasOne
    {
        return $this->hasOne(OrderLineItem::class, 'draft_order_item_id');
    }
}
