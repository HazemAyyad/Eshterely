<?php

namespace App\Services\Payments;

use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;

/**
 * Determines whether an order is eligible to start payment.
 * Structured for future granular review states (needs_admin_review, needs_reprice, etc.).
 */
class PaymentEligibilityService
{
    /**
     * Check if order can start payment. Returns eligibility result with reason if not eligible.
     *
     * @return array{eligible: bool, error_key: string|null, message: string}
     */
    public function checkOrderEligibility(Order $order): array
    {
        if ($this->isOrderCancelled($order)) {
            return [
                'eligible' => false,
                'error_key' => 'order_cancelled',
                'message' => 'Order is cancelled and cannot be paid.',
            ];
        }

        if ($order->status !== Order::STATUS_PENDING_PAYMENT) {
            return [
                'eligible' => false,
                'error_key' => 'invalid_order_status',
                'message' => 'Order status does not allow payment. Only orders with status pending_payment can start payment.',
            ];
        }

        $hasPaidPayment = $order->payments()
            ->where('status', PaymentStatus::Paid)
            ->exists();

        if ($hasPaidPayment) {
            $due = (float) ($order->amount_due_now ?? 0);
            $hasCardPaid = $order->payments()
                ->where('status', PaymentStatus::Paid)
                ->whereIn('provider', ['square', 'stripe'])
                ->exists();

            // Wallet (or similar) may be recorded as paid while amount_due_now > 0; allow gateway for the remainder.
            if ($due <= 0.00001 || $hasCardPaid) {
                return [
                    'eligible' => false,
                    'error_key' => 'already_paid',
                    'message' => 'Order is already paid.',
                ];
            }
        }

        if ($this->hasUnresolvedBlockingIssue($order)) {
            return [
                'eligible' => false,
                'error_key' => 'not_eligible_for_payment',
                'message' => 'Order has unresolved issues and is not eligible for payment.',
            ];
        }

        return [
            'eligible' => true,
            'error_key' => null,
            'message' => 'Order is eligible for payment.',
        ];
    }

    protected function isOrderCancelled(Order $order): bool
    {
        return $order->status === Order::STATUS_CANCELLED;
    }

    /**
     * Check for blocking issues. Extend here for future needs_admin_review, needs_reprice, needs_shipping_completion.
     */
    protected function hasUnresolvedBlockingIssue(Order $order): bool
    {
        // Currently we allow payment if order passed checkout readiness (pending_payment).
        // Future: check order.review_state for needs_admin_review, needs_reprice, needs_shipping_completion.
        return false;
    }
}
