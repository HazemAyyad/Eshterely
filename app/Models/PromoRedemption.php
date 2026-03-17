<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoRedemption extends Model
{
    protected $fillable = [
        'promo_code_id',
        'user_id',
        'order_id',
        'code_snapshot',
        'discount_type',
        'discount_value',
        'subtotal_amount',
        'shipping_amount',
        'total_before_amount',
        'discount_amount',
        'wallet_applied_amount',
        'total_after_amount',
        'status',
        'metadata',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total_before_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'wallet_applied_amount' => 'decimal:2',
            'total_after_amount' => 'decimal:2',
            'metadata' => 'array',
            'redeemed_at' => 'datetime',
        ];
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
