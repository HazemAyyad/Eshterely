<?php

namespace App\Services\Wallet;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\WalletRefund;

class WalletRefundableAmountService
{
    /**
     * Remaining refundable amount for an order (not wallet balance; tied to order total).
     */
    public function maxRefundableForOrder(Order $order): float
    {
        $total = round((float) $order->total_amount, 2);
        $reserved = WalletRefund::reservedAmountForSource(WalletRefund::SOURCE_ORDER, (int) $order->id);

        return round(max(0, $total - $reserved), 2);
    }

    /**
     * Remaining refundable for shipment shipping payment (paid shipments only).
     */
    public function maxRefundableForShipment(Shipment $shipment): float
    {
        if (! in_array($shipment->status, [
            Shipment::STATUS_PAID,
            Shipment::STATUS_PACKED,
            Shipment::STATUS_SHIPPED,
            Shipment::STATUS_DELIVERED,
        ], true)) {
            return 0.0;
        }

        $total = round((float) $shipment->total_shipping_payment, 2);
        $reserved = WalletRefund::reservedAmountForSource(WalletRefund::SOURCE_SHIPMENT, (int) $shipment->id);

        return round(max(0, $total - $reserved), 2);
    }
}
