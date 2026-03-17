<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ImportedProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_url',
        'store_key',
        'store_name',
        'country',
        'title',
        'image_url',
        'product_price',
        'product_currency',
        'package_info',
        'shipping_quote_snapshot',
        'final_pricing_snapshot',
        'carrier',
        'pricing_mode',
        'estimated',
        'missing_fields',
        'import_metadata',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'product_price' => 'decimal:2',
            'estimated' => 'boolean',
            'package_info' => 'array',
            'shipping_quote_snapshot' => 'array',
            'final_pricing_snapshot' => 'array',
            'missing_fields' => 'array',
            'import_metadata' => 'array',
        ];
    }

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ADDED_TO_CART = 'added_to_cart';
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_ARCHIVED = 'archived';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cartItem(): HasOne
    {
        return $this->hasOne(CartItem::class, 'imported_product_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isAddedToCart(): bool
    {
        return $this->status === self::STATUS_ADDED_TO_CART;
    }
}
