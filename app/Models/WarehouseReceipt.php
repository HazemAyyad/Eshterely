<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseReceipt extends Model
{
    protected $fillable = [
        'order_line_item_id',
        'received_at',
        'received_weight',
        'received_length',
        'received_width',
        'received_height',
        'images',
        'condition_notes',
        'special_handling_type',
        'additional_fee_amount',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'received_weight' => 'decimal:4',
            'received_length' => 'decimal:4',
            'received_width' => 'decimal:4',
            'received_height' => 'decimal:4',
            'images' => 'array',
            'additional_fee_amount' => 'decimal:2',
        ];
    }

    public function orderLineItem(): BelongsTo
    {
        return $this->belongsTo(OrderLineItem::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
