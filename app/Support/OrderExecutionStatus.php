<?php

namespace App\Support;

use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Models\OrderLineItem;
use Illuminate\Database\Eloquent\Builder;
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
     * Values exposed in admin/orders execution-status filter (same vocabulary as resolve()).
     *
     * @return list<string>
     */
    public static function filterableExecutionStatuses(): array
    {
        return [
            self::AWAITING_REVIEW,
            self::REVIEWED,
            self::AWAITING_PURCHASE,
            self::PARTIALLY_PURCHASED,
            self::FULLY_PURCHASED,
            self::IN_TRANSIT_TO_WAREHOUSE,
            self::PARTIALLY_AT_WAREHOUSE,
            self::FULLY_AT_WAREHOUSE,
            self::PARTIALLY_SHIPPED,
            self::FULLY_SHIPPED,
            self::DELIVERED,
            self::CANCELLED,
        ];
    }

    /**
     * Order IDs whose resolved execution status matches $target (same rules as resolve()).
     * Simple statuses use an exact SQL prefilter; others use the same prefilter + in-memory resolve on chunks.
     *
     * @return list<int>
     */
    public static function matchingOrderIdsForExecutionFilter(string $target): array
    {
        if (! in_array($target, self::filterableExecutionStatuses(), true)) {
            return [];
        }

        $base = Order::query()->select('orders.id');
        self::applyPrefilterForExecutionFilter($base, $target);

        if (in_array($target, [
            self::CANCELLED,
            self::AWAITING_REVIEW,
            self::AWAITING_PURCHASE,
            self::REVIEWED,
        ], true)) {
            return $base->orderBy('orders.id')->pluck('orders.id')->all();
        }

        $ids = [];
        $q = Order::query()
            ->select('orders.id')
            ->with(['payments', 'lineItems.shipmentItems.shipment']);
        self::applyPrefilterForExecutionFilter($q, $target);

        $q->orderBy('orders.id')->chunkById(500, function ($orders) use ($target, &$ids) {
            foreach ($orders as $order) {
                $hasPaid = $order->payments->contains(fn ($p) => $p->status->value === 'paid');
                if (self::resolve($order, $order->lineItems, $hasPaid) === $target) {
                    $ids[] = $order->id;
                }
            }
        });

        return $ids;
    }

    /**
     * SQL prefilter to shrink the candidate set before resolve() (exact for cancelled / review / purchase / reviewed).
     */
    private static function applyPrefilterForExecutionFilter(Builder $q, string $target): void
    {
        $paid = fn (Builder $p) => $p->where('status', PaymentStatus::Paid);

        switch ($target) {
            case self::CANCELLED:
                $q->where('orders.status', Order::STATUS_CANCELLED);
                return;
            case self::AWAITING_REVIEW:
                $q->where('orders.status', '!=', Order::STATUS_CANCELLED)
                    ->whereHas('payments', $paid)
                    ->where(function (Builder $qq) {
                        $qq->where('needs_review', true)
                            ->orWhereNull('reviewed_at');
                    });
                return;
            case self::AWAITING_PURCHASE:
                $q->where('orders.status', '!=', Order::STATUS_CANCELLED)
                    ->whereHas('payments', $paid)
                    ->whereNotNull('reviewed_at')
                    ->where('needs_review', false)
                    ->whereDoesntHave('lineItems');
                return;
            case self::REVIEWED:
                $q->where('orders.status', '!=', Order::STATUS_CANCELLED)
                    ->whereHas('payments', $paid)
                    ->whereNotNull('reviewed_at')
                    ->where('needs_review', false)
                    ->whereHas('lineItems')
                    ->whereDoesntHave('lineItems', function (Builder $line) {
                        $line->whereNotIn('fulfillment_status', [
                            OrderLineItem::FULFILLMENT_PAID,
                            OrderLineItem::FULFILLMENT_REVIEWED,
                        ]);
                    });
                return;
            default:
                $q->where('orders.status', '!=', Order::STATUS_CANCELLED)
                    ->whereHas('payments', $paid)
                    ->whereNotNull('reviewed_at')
                    ->where('needs_review', false)
                    ->has('lineItems');
        }
    }

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
