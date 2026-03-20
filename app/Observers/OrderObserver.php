<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Fcm\OrderShipmentNotificationTrigger;

/**
 * Triggers FCM notifications for order lifecycle events.
 * Notification logic lives in OrderShipmentNotificationTrigger; observer only wires the event.
 */
class OrderObserver
{
    public function __construct(
        protected OrderShipmentNotificationTrigger $notificationTrigger
    ) {}

    /**
     * After an order is created, send "Order Created" push to the order owner.
     */
    public function created(Order $order): void
    {
        // Do not notify at order creation time to avoid premature "order added"
        // pushes before hosted checkout is completed. Payment-success notifications
        // are dispatched from payment webhooks.
    }
}
