<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Str;

class PaymentReferenceGenerator
{
    /**
     * Generate a unique human-friendly reference: PAY-YYYYMMDD-XXXXXX
     */
    public function generate(): string
    {
        $date = now()->format('Ymd');
        $suffix = strtoupper(Str::random(6));

        $candidate = "PAY-{$date}-{$suffix}";

        if (Payment::where('reference', $candidate)->exists()) {
            return $this->generate();
        }

        return $candidate;
    }
}
