<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DraftOrder extends Model
{
    public const STATUS_DRAFT = 'draft';

    /** Future granular review states (keys in review_state json): needs_admin_review, needs_reprice, needs_shipping_completion */
    public const REVIEW_STATE_NEEDS_ADMIN_REVIEW = 'needs_admin_review';
    public const REVIEW_STATE_NEEDS_REPRICE = 'needs_reprice';
    public const REVIEW_STATE_NEEDS_SHIPPING_COMPLETION = 'needs_shipping_completion';

    protected $fillable = [
        'user_id',
        'status',
        'currency',
        'subtotal_snapshot',
        'shipping_total_snapshot',
        'service_fee_total_snapshot',
        'final_total_snapshot',
        'estimated',
        'needs_review',
        'review_state',
        'notes',
        'warnings',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_snapshot' => 'decimal:2',
            'shipping_total_snapshot' => 'decimal:2',
            'service_fee_total_snapshot' => 'decimal:2',
            'final_total_snapshot' => 'decimal:2',
            'estimated' => 'boolean',
            'needs_review' => 'boolean',
            'review_state' => 'array',
            'notes' => 'array',
            'warnings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DraftOrderItem::class, 'draft_order_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'draft_order_id');
    }
}
