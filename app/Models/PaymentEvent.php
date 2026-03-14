<?php

namespace App\Models;

use App\Enums\Payment\PaymentEventSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    protected $fillable = [
        'payment_id',
        'source',
        'event_type',
        'payload',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'source' => PaymentEventSource::class,
            'payload' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
