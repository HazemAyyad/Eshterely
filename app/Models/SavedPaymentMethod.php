<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedPaymentMethod extends Model
{
    public const STATUS_PENDING = 'pending_verification';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed_verification';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'user_id',
        'stripe_customer_id',
        'stripe_payment_method_id',
        'brand',
        'last4',
        'exp_month',
        'exp_year',
        'is_default',
        'verification_status',
        'verification_charge_amount',
        'verification_attempts',
        'stripe_verification_payment_intent_id',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'verification_charge_amount' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsableForTopUp(): bool
    {
        return $this->verification_status === self::STATUS_VERIFIED;
    }
}
