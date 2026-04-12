<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTopupRequest extends Model
{
    public const METHOD_WIRE = 'wire_transfer';

    public const METHOD_ZELLE = 'zelle';

    public const STATUS_PENDING = 'pending';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'wallet_topup_requests';

    protected $fillable = [
        'user_id',
        'method',
        'amount',
        'currency',
        'reference',
        'sender_name',
        'sender_email',
        'sender_phone',
        'bank_name',
        'proof_file',
        'notes',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'approved_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'metadata' => 'array',
        ];
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
