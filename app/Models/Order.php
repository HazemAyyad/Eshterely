<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Order extends Model
{
    use HasFactory;
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PURCHASED = 'purchased';
    public const STATUS_SHIPPED_TO_WAREHOUSE = 'shipped_to_warehouse';
    public const STATUS_INTERNATIONAL_SHIPPING = 'international_shipping';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    /** Operational review classification keys (in review_state json): needs_admin_review, needs_reprice, needs_shipping_completion */
    public const REVIEW_STATE_NEEDS_ADMIN_REVIEW = 'needs_admin_review';
    public const REVIEW_STATE_NEEDS_REPRICE = 'needs_reprice';
    public const REVIEW_STATE_NEEDS_SHIPPING_COMPLETION = 'needs_shipping_completion';

    protected $fillable = [
        'user_id', 'draft_order_id', 'order_number', 'origin', 'status', 'placed_at', 'delivered_at',
        'total_amount', 'currency', 'order_total_snapshot', 'shipping_total_snapshot', 'service_fee_snapshot',
        'promo_code_id', 'promo_code', 'promo_discount_amount', 'wallet_applied_amount', 'amount_due_now',
        'estimated', 'needs_review', 'review_state', 'admin_notes', 'reviewed_at',
        'refund_status', 'estimated_delivery', 'shipping_address_id', 'consolidation_savings',
        'payment_method_label', 'payment_method_last_four', 'invoice_issue_date', 'transaction_id',
        'shipping_address_text',
    ];

    protected function casts(): array
    {
        return [
            'placed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'invoice_issue_date' => 'date',
            'total_amount' => 'decimal:2',
            'order_total_snapshot' => 'decimal:2',
            'shipping_total_snapshot' => 'decimal:2',
            'service_fee_snapshot' => 'decimal:2',
            'promo_discount_amount' => 'decimal:2',
            'wallet_applied_amount' => 'decimal:2',
            'amount_due_now' => 'decimal:2',
            'estimated' => 'boolean',
            'needs_review' => 'boolean',
            'review_state' => 'array',
            'reviewed_at' => 'datetime',
            'consolidation_savings' => 'decimal:2',
        ];
    }

    public function operationLogs(): HasMany
    {
        return $this->hasMany(OrderOperationLog::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function draftOrder(): BelongsTo
    {
        return $this->belongsTo(DraftOrder::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(OrderShipment::class);
    }

    public function lineItems(): HasManyThrough
    {
        return $this->hasManyThrough(OrderLineItem::class, OrderShipment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
