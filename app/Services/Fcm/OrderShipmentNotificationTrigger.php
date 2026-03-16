<?php

namespace App\Services\Fcm;

use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\Payment;
use App\Services\Fcm\FcmNotificationService;

/**
 * Central place to dispatch FCM for order/payment/shipment lifecycle events.
 * Called from webhook, admin order, and shipment services. Uses real system state.
 */
class OrderShipmentNotificationTrigger
{
    public function __construct(
        protected NotificationDispatchService $dispatchService
    ) {}

    /**
     * Payment successful: notify order's user.
     */
    public function onPaymentSuccess(Payment $payment): void
    {
        $order = $payment->order;
        if ($order === null) {
            return;
        }
        $user = $order->user;
        if ($user === null) {
            return;
        }
        $this->dispatchService->sendSystemEvent(
            __('notifications.payment_success_title', [], 'en'),
            __('notifications.payment_success_body', ['order_number' => $order->order_number ?? $order->id], 'en'),
            $user,
            FcmNotificationService::dataPayload('order', (string) $order->id, 'order_detail', ['order_id' => $order->id]),
            (int) $order->id,
            null,
            ['event' => 'payment_success', 'payment_id' => $payment->id]
        );
    }

    /**
     * Order moved to processing: notify order's user.
     */
    public function onOrderProcessing(Order $order): void
    {
        $user = $order->user;
        if ($user === null) {
            return;
        }
        $this->dispatchService->sendSystemEvent(
            __('notifications.order_processing_title', [], 'en'),
            __('notifications.order_processing_body', ['order_number' => $order->order_number ?? $order->id], 'en'),
            $user,
            FcmNotificationService::dataPayload('order', (string) $order->id, 'order_detail', ['order_id' => $order->id]),
            (int) $order->id,
            null,
            ['event' => 'order_processing']
        );
    }

    /**
     * Shipment tracking assigned: notify order's user.
     */
    public function onShipmentTrackingAssigned(OrderShipment $shipment): void
    {
        $order = $shipment->order;
        if ($order === null) {
            return;
        }
        $user = $order->user;
        if ($user === null) {
            return;
        }
        $this->dispatchService->sendSystemEvent(
            __('notifications.shipment_tracking_title', [], 'en'),
            __('notifications.shipment_tracking_body', [
                'order_number' => $order->order_number ?? $order->id,
                'tracking' => $shipment->tracking_number ?? '',
            ], 'en'),
            $user,
            FcmNotificationService::dataPayload('shipment', (string) $shipment->id, 'order_detail', ['order_id' => $order->id, 'shipment_id' => $shipment->id]),
            (int) $order->id,
            (int) $shipment->id,
            ['event' => 'shipment_tracking', 'tracking_number' => $shipment->tracking_number]
        );
    }

    /**
     * Shipment delivered: notify order's user.
     */
    public function onShipmentDelivered(OrderShipment $shipment): void
    {
        $order = $shipment->order;
        if ($order === null) {
            return;
        }
        $user = $order->user;
        if ($user === null) {
            return;
        }
        $this->dispatchService->sendSystemEvent(
            __('notifications.shipment_delivered_title', [], 'en'),
            __('notifications.shipment_delivered_body', ['order_number' => $order->order_number ?? $order->id], 'en'),
            $user,
            FcmNotificationService::dataPayload('order', (string) $order->id, 'order_detail', ['order_id' => $order->id]),
            (int) $order->id,
            (int) $shipment->id,
            ['event' => 'shipment_delivered']
        );
    }
}
