<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_PACKED = 'packed';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_DELIVERED = 'delivered';

    protected $fillable = [
        'user_id',
        'destination_address_id',
        'status',
        'carrier',
        'tracking_number',
        'final_weight',
        'final_length',
        'final_width',
        'final_height',
        'final_box_image',
        'dispatched_at',
        'delivered_at',
        'shipping_cost',
        'additional_fees_total',
        'total_shipping_payment',
        'currency',
        'pricing_breakdown',
        'draft_payload',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'final_weight' => 'decimal:4',
            'final_length' => 'decimal:4',
            'final_width' => 'decimal:4',
            'final_height' => 'decimal:4',
            'shipping_cost' => 'decimal:2',
            'additional_fees_total' => 'decimal:2',
            'total_shipping_payment' => 'decimal:2',
            'pricing_breakdown' => 'array',
            'draft_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function destinationAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'destination_address_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
