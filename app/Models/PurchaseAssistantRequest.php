<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseAssistantRequest extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_AWAITING_CUSTOMER_PAYMENT = 'awaiting_customer_payment';

    public const STATUS_PAYMENT_UNDER_REVIEW = 'payment_under_review';

    public const STATUS_PAID = 'paid';

    public const STATUS_PURCHASING = 'purchasing';

    public const STATUS_PURCHASED = 'purchased';

    public const STATUS_IN_TRANSIT_TO_WAREHOUSE = 'in_transit_to_warehouse';

    public const STATUS_RECEIVED_AT_WAREHOUSE = 'received_at_warehouse';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const ORIGIN_PURCHASE_ASSISTANT = 'purchase_assistant';

    /**
     * @var list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_SUBMITTED,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_AWAITING_CUSTOMER_PAYMENT,
            self::STATUS_PAYMENT_UNDER_REVIEW,
            self::STATUS_PAID,
            self::STATUS_PURCHASING,
            self::STATUS_PURCHASED,
            self::STATUS_IN_TRANSIT_TO_WAREHOUSE,
            self::STATUS_RECEIVED_AT_WAREHOUSE,
            self::STATUS_COMPLETED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
        ];
    }

    protected $fillable = [
        'user_id',
        'source_url',
        'source_domain',
        'store_display_name',
        'title',
        'details',
        'quantity',
        'variant_details',
        'customer_estimated_price',
        'currency',
        'image_paths',
        'admin_product_price',
        'admin_service_fee',
        'admin_notes',
        'status',
        'origin',
        'converted_order_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'customer_estimated_price' => 'decimal:2',
            'admin_product_price' => 'decimal:2',
            'admin_service_fee' => 'decimal:2',
            'image_paths' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }
}
