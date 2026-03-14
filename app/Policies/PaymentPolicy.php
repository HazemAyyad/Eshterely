<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * User can view a payment only if it belongs to them (via user_id or order.user_id).
     */
    public function view(User $user, Payment $payment): bool
    {
        if ($payment->user_id !== null) {
            return $payment->user_id === $user->id;
        }

        return $payment->order && $payment->order->user_id === $user->id;
    }
}
