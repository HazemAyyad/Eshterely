<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'user_id', 'product_url', 'name', 'unit_price', 'quantity', 'currency',
        'image_url', 'store_key', 'store_name', 'product_id', 'country',
        'weight', 'weight_unit', 'length', 'width', 'height', 'dimension_unit',
        'source', 'variation_text', 'review_status', 'shipping_cost',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'shipping_cost' => 'decimal:2',
            'weight' => 'decimal:4',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
