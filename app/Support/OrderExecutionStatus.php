<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderLineItem;
use Illuminate\Support\Collection;

/**
 * Single source of truth for order execution stage (admin + mobile).
 * Derived from payments, review gate, and line-item / outbound shipment state.
 * Internal DB `orders.status` is not the primary UX status — use resolve() instead.
 */
final class OrderExecutionStatus
{
    public const AWAITING_PAYMENT = 'awaiting_payment';

    public const AWAITING_REVIEW = 'awaiting_review';

    public const REVIEWED = 'reviewed';

    public const AWAITING_PURCHASE = 'awaiting_purchase';

    public const PARTIALLY_PURCHASED = 'partially_purchased';

    public const FULLY_PURCHASED = 'fully_purchased';

    public const IN_TRANSIT_TO_WAREHOUSE = 'in_transit_to_warehouse';

    public const PARTIALLY_AT_WAREHOUSE = 'partially_at_warehouse';

    public const FULLY_AT_WAREHOUSE = 'fully_at_warehouse';

    public const PARTIALLY_SHIPPED = 'partially_shipped';

    public const FULLY_SHIPPED = 'fully_shipped';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    /**
     * @param  Collection<int, \App\Models\OrderLineItem>  $items
     */
    public static function resolve(Order $order, Collection $items, bool $hasPaidCheckout): string
    {
        if ($order->status === Order::STATUS_CANCELLED) {
            return self::CANCELLED;
        }

        if (! $hasPaidCheckout) {
            return self::AWAITING_PAYMENT;
        }

        if ($order->needs_review || $order->reviewed_at === null) {
            return self::AWAITING_REVIEW;
        }

        if ($items->isEmpty()) {
            return self::AWAITING_PURCHASE;
        }

        $allPaidOrReviewedOnly = $items->every(fn ($li) => in_array($li->fulfillment_status, [
            OrderLineItem::FULFILLMENT_PAID,
            OrderLineItem::FULFILLMENT_REVIEWED,
        ], true));

        if ($allPaidOrReviewedOnly) {
            return self::REVIEWED;
        }

        $derived = AdminOrderFulfillmentPresenter::deriveOrderFulfillmentState($items);

        if ($derived === 'fully_purchased') {
            $refined = self::refineFullyPurchasedAggregate($items);
            if ($refined !== null) {
                return $refined;
            }
        }

        return match ($derived) {
            'no_items' => self::AWAITING_PURCHASE,
            'awaiting_purchase' => self::AWAITING_PURCHASE,
            'partially_purchased' => self::PARTIALLY_PURCHASED,
            'fully_purchased' => self::FULLY_PURCHASED,
            'partially_at_warehouse' => self::PARTIALLY_AT_WAREHOUSE,
            'fully_at_warehouse' => self::FULLY_AT_WAREHOUSE,
            'partially_shipped' => self::PARTIALLY_SHIPPED,
            'fully_shipped' => self::FULLY_SHIPPED,
            'delivered' => self::DELIVERED,
            default => self::AWAITING_PURCHASE,
        };
    }

    /**
     * When every line is past paid/reviewed, deriveOrderFulfillmentState() returns `fully_purchased`
     * even if lines are only in transit. Split that case using line-level fulfillment_status.
     *
     * @param  Collection<int, OrderLineItem>  $items
     */
    private static function refineFullyPurchasedAggregate(Collection $items): ?string
    {
        $n = $items->count();

        $inTransit = $items->filter(fn ($li) => $li->fulfillment_status === OrderLineItem::FULFILLMENT_IN_TRANSIT_TO_WAREHOUSE)->count();
        $purchasedOnly = $items->filter(fn ($li) => $li->fulfillment_status === OrderLineItem::FULFILLMENT_PURCHASED)->count();
        $atWarehouse = $items->filter(fn ($li) => in_array($li->fulfillment_status, [
            OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE,
            OrderLineItem::FULFILLMENT_READY_FOR_SHIPMENT,
        ], true))->count();

        if ($inTransit === $n) {
            return self::IN_TRANSIT_TO_WAREHOUSE;
        }

        if ($purchasedOnly === $n) {
            return self::FULLY_PURCHASED;
        }

        if ($inTransit > 0 && $inTransit < $n) {
            return self::PARTIALLY_PURCHASED;
        }

        if ($purchasedOnly > 0 && $purchasedOnly < $n && $atWarehouse === 0) {
            return self::PARTIALLY_PURCHASED;
        }

        return null;
    }

    public static function badgeClass(string $executionStatus): string
    {
        return match ($executionStatus) {
            self::DELIVERED, self::REVIEWED => 'success',
            self::FULLY_SHIPPED, self::FULLY_PURCHASED, self::FULLY_AT_WAREHOUSE => 'info',
            self::PARTIALLY_SHIPPED, self::PARTIALLY_PURCHASED, self::PARTIALLY_AT_WAREHOUSE => 'warning',
            self::CANCELLED => 'danger',
            self::AWAITING_PAYMENT, self::AWAITING_REVIEW => 'secondary',
            self::AWAITING_PURCHASE => 'secondary',
            self::IN_TRANSIT_TO_WAREHOUSE => 'primary',
            default => 'secondary',
        };
    }
}
