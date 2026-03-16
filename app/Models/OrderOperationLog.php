<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderOperationLog extends Model
{
    public const ACTION_STATUS_CHANGE = 'status_change';
    public const ACTION_REVIEW = 'review';
    public const ACTION_SHIPPING_OVERRIDE = 'shipping_override';
    public const ACTION_REPRICE_NOTE = 'reprice_note';
    public const ACTION_SHIPMENT_TRACKING_ASSIGNED = 'shipment_tracking_assigned';
    public const ACTION_SHIPMENT_CARRIER_CHANGED = 'shipment_carrier_changed';
    public const ACTION_SHIPMENT_STATUS_UPDATED = 'shipment_status_updated';
    public const ACTION_SHIPMENT_DELIVERED = 'shipment_delivered';
    public const ACTION_SHIPMENT_EVENT_ADDED = 'shipment_event_added';
    public const ACTION_SHIPMENT_ESTIMATED_DELIVERY_SET = 'shipment_estimated_delivery_set';

    protected $fillable = [
        'order_id',
        'admin_id',
        'action',
        'payload',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
