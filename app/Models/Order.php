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

    protected $fillable = [
        'user_id', 'draft_order_id', 'order_number', 'origin', 'status', 'placed_at', 'delivered_at',
        'total_amount', 'currency', 'order_total_snapshot', 'shipping_total_snapshot', 'service_fee_snapshot',
        'estimated', 'needs_review',
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
            'estimated' => 'boolean',
            'needs_review' => 'boolean',
            'consolidation_savings' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function draftOrder(): BelongsTo
    {
        return $this->belongsTo(DraftOrder::class);
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
