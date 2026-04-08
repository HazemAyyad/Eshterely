<?php

namespace App\Support;

use App\Models\OrderLineItem;
use App\Models\Shipment;
use Illuminate\Support\Collection;

/**
 * Read-only aggregation of line-item fulfillment for admin order detail (presentation only).
 */
class AdminOrderFulfillmentPresenter
{
    /**
     * @return array{fulfillment_summary: array, fulfillment_stages: list<array{id: string, done: bool}>, order_fulfillment_state: string}
     */
    public static function forOrder(Collection $items, bool $hasPaidCheckout): array
    {
        $summary = self::buildSummaryCounts($items);
        $stages = self::buildStagesAllItems($items, $hasPaidCheckout);
        $state = self::deriveOrderFulfillmentState($items);

        return [
            'fulfillment_summary' => $summary,
            'fulfillment_stages' => $stages,
            'order_fulfillment_state' => $state,
        ];
    }

    /**
     * Single order-level label — most advanced milestone reached by the whole order (all items considered).
     */
    public static function deriveOrderFulfillmentState(Collection $items): string
    {
        if ($items->isEmpty()) {
            return 'no_items';
        }

        $n = $items->count();

        $awaitingPurchase = $items->filter(fn ($li) => in_array($li->fulfillment_status, [
            OrderLineItem::FULFILLMENT_PAID,
            OrderLineItem::FULFILLMENT_REVIEWED,
        ], true))->count();

        $atWarehouse = $items->filter(fn ($li) => in_array($li->fulfillment_status, [
            OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE,
            OrderLineItem::FULFILLMENT_READY_FOR_SHIPMENT,
        ], true))->count();

        $allShipmentsDelivered = self::allOutboundShipmentsInStatuses($items, [Shipment::STATUS_DELIVERED]);
        $allShipmentsShippedOrDelivered = self::allOutboundShipmentsInStatuses($items, [
            Shipment::STATUS_SHIPPED,
            Shipment::STATUS_DELIVERED,
        ]);
        $anyShippedOrDelivered = self::anyOutboundShipmentInStatuses($items, [
            Shipment::STATUS_SHIPPED,
            Shipment::STATUS_DELIVERED,
        ]);

        $everyItemHasOutbound = $items->every(fn ($li) => $li->shipmentItems->isNotEmpty());

        // Outbound milestones (only when every line has been placed on a customer shipment)
        if ($everyItemHasOutbound) {
            if ($allShipmentsDelivered) {
                return 'delivered';
            }
            if ($allShipmentsShippedOrDelivered) {
                return 'fully_shipped';
            }
            if ($anyShippedOrDelivered) {
                return 'partially_shipped';
            }
        }

        if ($atWarehouse === $n) {
            return 'fully_at_warehouse';
        }
        if ($atWarehouse > 0 && $atWarehouse < $n) {
            return 'partially_at_warehouse';
        }

        if ($awaitingPurchase === 0) {
            return 'fully_purchased';
        }
        if ($awaitingPurchase > 0 && $awaitingPurchase < $n) {
            return 'partially_purchased';
        }

        return 'awaiting_purchase';
    }

    /**
     * @param  list<string>  $statuses
     */
    private static function allOutboundShipmentsInStatuses(Collection $items, array $statuses): bool
    {
        foreach ($items as $li) {
            if ($li->shipmentItems->isEmpty()) {
                return false;
            }
            foreach ($li->shipmentItems as $si) {
                $s = $si->shipment;
                if (! $s || ! in_array($s->status, $statuses, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $statuses
     */
    private static function anyOutboundShipmentInStatuses(Collection $items, array $statuses): bool
    {
        foreach ($items as $li) {
            foreach ($li->shipmentItems as $si) {
                $s = $si->shipment;
                if ($s && in_array($s->status, $statuses, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Counts per column in the summary card row.
     */
    public static function buildSummaryCounts(Collection $items): array
    {
        return [
            'total_items' => $items->count(),
            'awaiting_purchase' => $items->whereIn('fulfillment_status', [
                OrderLineItem::FULFILLMENT_PAID,
                OrderLineItem::FULFILLMENT_REVIEWED,
            ])->count(),
            'purchased' => $items->where('fulfillment_status', OrderLineItem::FULFILLMENT_PURCHASED)->count(),
            'in_transit' => $items->where('fulfillment_status', OrderLineItem::FULFILLMENT_IN_TRANSIT_TO_WAREHOUSE)->count(),
            'arrived' => $items->where('fulfillment_status', OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE)->count(),
            'ready_for_shipment' => $items->where('fulfillment_status', OrderLineItem::FULFILLMENT_READY_FOR_SHIPMENT)->count(),
            'at_warehouse' => $items->whereIn('fulfillment_status', [
                OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE,
                OrderLineItem::FULFILLMENT_READY_FOR_SHIPMENT,
            ])->count(),
            'assigned_outbound' => $items->filter(fn ($li) => $li->shipmentItems->isNotEmpty())->count(),
            'outbound_shipped' => $items->filter(function ($li) {
                foreach ($li->shipmentItems as $si) {
                    $s = $si->shipment;
                    if ($s && in_array($s->status, [Shipment::STATUS_SHIPPED, Shipment::STATUS_DELIVERED], true)) {
                        return true;
                    }
                }

                return false;
            })->count(),
        ];
    }

    /**
     * Stage strip: each stage is "done" only when every line item satisfies that stage (order-level complete).
     *
     * @return list<array{id: string, done: bool}>
     */
    public static function buildStagesAllItems(Collection $items, bool $hasPaidCheckout): array
    {
        if ($items->isEmpty()) {
            return [
                ['id' => 'paid', 'done' => $hasPaidCheckout],
                ['id' => 'purchased', 'done' => false],
                ['id' => 'in_transit_wh', 'done' => false],
                ['id' => 'arrived_wh', 'done' => false],
                ['id' => 'assigned_outbound', 'done' => false],
                ['id' => 'packed_shipped', 'done' => false],
            ];
        }

        $allPastProcurement = $items->every(fn ($li) => ! in_array($li->fulfillment_status, [
            OrderLineItem::FULFILLMENT_PAID,
            OrderLineItem::FULFILLMENT_REVIEWED,
        ], true));

        $allInTransitOrLater = $items->every(fn ($li) => in_array($li->fulfillment_status, [
            OrderLineItem::FULFILLMENT_IN_TRANSIT_TO_WAREHOUSE,
            OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE,
            OrderLineItem::FULFILLMENT_READY_FOR_SHIPMENT,
        ], true));

        $allArrivedOrReady = $items->every(fn ($li) => in_array($li->fulfillment_status, [
            OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE,
            OrderLineItem::FULFILLMENT_READY_FOR_SHIPMENT,
        ], true));

        $allAssignedOutbound = $items->every(fn ($li) => $li->shipmentItems->isNotEmpty());

        $allPackedOrShippedOutbound = $items->every(function ($li) {
            if ($li->shipmentItems->isEmpty()) {
                return false;
            }
            foreach ($li->shipmentItems as $si) {
                $s = $si->shipment;
                if (! $s || ! in_array($s->status, [
                    Shipment::STATUS_PACKED,
                    Shipment::STATUS_SHIPPED,
                    Shipment::STATUS_DELIVERED,
                ], true)) {
                    return false;
                }
            }

            return true;
        });

        return [
            [
                'id' => 'paid',
                'done' => $hasPaidCheckout,
            ],
            [
                'id' => 'purchased',
                'done' => $allPastProcurement,
            ],
            [
                'id' => 'in_transit_wh',
                'done' => $allInTransitOrLater,
            ],
            [
                'id' => 'arrived_wh',
                'done' => $allArrivedOrReady,
            ],
            [
                'id' => 'assigned_outbound',
                'done' => $allAssignedOutbound,
            ],
            [
                'id' => 'packed_shipped',
                'done' => $allPackedOrShippedOutbound,
            ],
        ];
    }
}
