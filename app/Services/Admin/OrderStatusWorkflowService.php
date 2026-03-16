<?php

namespace App\Services\Admin;

use App\Enums\Payment\PaymentStatus;
use App\Models\Order;

/**
 * Payment-aware order status transitions for admin. Unpaid orders cannot enter fulfillment.
 */
class OrderStatusWorkflowService
{
    /** Statuses that require payment to be completed. */
    public const FULFILLMENT_STATUSES = [
        Order::STATUS_UNDER_REVIEW,
        Order::STATUS_APPROVED,
        Order::STATUS_PROCESSING,
        Order::STATUS_PURCHASED,
        Order::STATUS_SHIPPED_TO_WAREHOUSE,
        Order::STATUS_INTERNATIONAL_SHIPPING,
        Order::STATUS_IN_TRANSIT,
        Order::STATUS_DELIVERED,
    ];

    /** Allowed next statuses from each status. Cancelled is terminal. */
    private const TRANSITIONS = [
        Order::STATUS_PENDING_PAYMENT => [Order::STATUS_PAID, Order::STATUS_CANCELLED],
        Order::STATUS_PAID => [
            Order::STATUS_UNDER_REVIEW, Order::STATUS_APPROVED, Order::STATUS_PROCESSING,
            Order::STATUS_CANCELLED,
        ],
        Order::STATUS_UNDER_REVIEW => [
            Order::STATUS_APPROVED, Order::STATUS_PROCESSING, Order::STATUS_PAID, Order::STATUS_CANCELLED,
        ],
        Order::STATUS_APPROVED => [
            Order::STATUS_PROCESSING, Order::STATUS_PURCHASED, Order::STATUS_CANCELLED,
        ],
        Order::STATUS_PROCESSING => [
            Order::STATUS_PURCHASED, Order::STATUS_SHIPPED_TO_WAREHOUSE, Order::STATUS_CANCELLED,
        ],
        Order::STATUS_PURCHASED => [
            Order::STATUS_SHIPPED_TO_WAREHOUSE, Order::STATUS_INTERNATIONAL_SHIPPING, Order::STATUS_CANCELLED,
        ],
        Order::STATUS_SHIPPED_TO_WAREHOUSE => [
            Order::STATUS_INTERNATIONAL_SHIPPING, Order::STATUS_IN_TRANSIT, Order::STATUS_CANCELLED,
        ],
        Order::STATUS_INTERNATIONAL_SHIPPING => [Order::STATUS_IN_TRANSIT, Order::STATUS_CANCELLED],
        Order::STATUS_IN_TRANSIT => [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED],
        Order::STATUS_DELIVERED => [],
        Order::STATUS_CANCELLED => [],
    ];

    /**
     * Check if order can transition to the given status. Payment-aware: unpaid cannot enter fulfillment.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function canTransitionTo(Order $order, string $newStatus): array
    {
        if ($order->status === $newStatus) {
            return ['allowed' => false, 'reason' => 'Order is already in this status.'];
        }

        if ($order->status === Order::STATUS_CANCELLED) {
            return ['allowed' => false, 'reason' => 'Cancelled orders cannot change status.'];
        }

        $allowed = self::TRANSITIONS[$order->status] ?? [];
        if (! in_array($newStatus, $allowed, true)) {
            return ['allowed' => false, 'reason' => "Transition from {$order->status} to {$newStatus} is not allowed."];
        }

        if (in_array($newStatus, self::FULFILLMENT_STATUSES, true)) {
            $hasPaid = $order->payments()->where('status', PaymentStatus::Paid)->exists();
            if (! $hasPaid && $order->status !== Order::STATUS_PAID) {
                return ['allowed' => false, 'reason' => 'Order must be paid before entering fulfillment statuses.'];
            }
        }

        return ['allowed' => true, 'reason' => null];
    }

    /** All statuses valid for admin selection. */
    public function allStatuses(): array
    {
        return [
            Order::STATUS_PENDING_PAYMENT,
            Order::STATUS_PAID,
            Order::STATUS_UNDER_REVIEW,
            Order::STATUS_APPROVED,
            Order::STATUS_PROCESSING,
            Order::STATUS_PURCHASED,
            Order::STATUS_SHIPPED_TO_WAREHOUSE,
            Order::STATUS_INTERNATIONAL_SHIPPING,
            Order::STATUS_IN_TRANSIT,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
        ];
    }
}
