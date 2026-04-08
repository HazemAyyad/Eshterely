<?php

namespace App\Support;

use App\Models\OrderLineItem;
use App\Models\Shipment;

/**
 * Display-only labels and Bootstrap badge classes for admin UI.
 * Internal / DB values stay unchanged.
 */
class AdminFulfillmentLabels
{
    /**
     * @return array{label: string, badge: string}
     */
    public static function lineItemFulfillment(?string $status): array
    {
        $label = match ($status) {
            OrderLineItem::FULFILLMENT_PAID => 'Awaiting purchase',
            OrderLineItem::FULFILLMENT_REVIEWED => 'Reviewed',
            OrderLineItem::FULFILLMENT_PURCHASED => 'Purchased',
            OrderLineItem::FULFILLMENT_IN_TRANSIT_TO_WAREHOUSE => 'In transit to WH',
            OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE => 'At warehouse',
            OrderLineItem::FULFILLMENT_READY_FOR_SHIPMENT => 'Ready to ship',
            null, '' => '—',
            default => (string) $status,
        };

        $badge = match ($status) {
            OrderLineItem::FULFILLMENT_PAID => 'secondary',
            OrderLineItem::FULFILLMENT_REVIEWED => 'info',
            OrderLineItem::FULFILLMENT_PURCHASED => 'primary',
            OrderLineItem::FULFILLMENT_IN_TRANSIT_TO_WAREHOUSE => 'warning',
            OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE => 'success',
            OrderLineItem::FULFILLMENT_READY_FOR_SHIPMENT => 'success',
            default => 'secondary',
        };

        return ['label' => $label, 'badge' => $badge];
    }

    /**
     * @return array{label: string, badge: string}
     */
    public static function outboundShipment(?string $status): array
    {
        $label = match ($status) {
            Shipment::STATUS_DRAFT => 'Draft',
            Shipment::STATUS_AWAITING_PAYMENT => 'Awaiting shipping payment',
            Shipment::STATUS_PAID => 'Shipping paid',
            Shipment::STATUS_PACKED => 'Packed',
            Shipment::STATUS_SHIPPED => 'Shipped',
            Shipment::STATUS_DELIVERED => 'Delivered',
            null, '' => '—',
            default => (string) $status,
        };

        $badge = match ($status) {
            Shipment::STATUS_DRAFT => 'secondary',
            Shipment::STATUS_AWAITING_PAYMENT => 'warning',
            Shipment::STATUS_PAID => 'info',
            Shipment::STATUS_PACKED => 'primary',
            Shipment::STATUS_SHIPPED => 'success',
            Shipment::STATUS_DELIVERED => 'success',
            default => 'secondary',
        };

        return ['label' => $label, 'badge' => $badge];
    }
}
