<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletWithdrawal extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_TRANSFERRED = 'transferred';

    protected $fillable = [
        'user_id',
        'amount',
        'fee_percent',
        'fee_amount',
        'net_amount',
        'iban',
        'bank_name',
        'country',
        'note',
        'status',
        'admin_notes',
        'transfer_proof',
        'reviewed_by',
        'reviewed_at',
        'transferred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_percent' => 'decimal:4',
            'fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'transferred_at' => 'datetime',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_TRANSFERRED,
        ];
    }

    /** Amounts reserving balance until transfer or rejection. */
    public static function reservedAmountForUser(int $userId): float
    {
        return (float) static::query()
            ->where('user_id', $userId)
            ->whereIn('status', [
                self::STATUS_PENDING,
                self::STATUS_UNDER_REVIEW,
                self::STATUS_APPROVED,
            ])
            ->sum('amount');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}
