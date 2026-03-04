<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'order_number', 'origin', 'status', 'placed_at', 'delivered_at',
        'total_amount', 'currency', 'refund_status', 'estimated_delivery',
        'shipping_address_id', 'consolidation_savings', 'payment_method_label',
        'payment_method_last_four', 'invoice_issue_date', 'transaction_id',
        'shipping_address_text',
    ];

    protected function casts(): array
    {
        return [
            'placed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'invoice_issue_date' => 'date',
            'total_amount' => 'decimal:2',
            'consolidation_savings' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(OrderShipment::class);
    }
}
