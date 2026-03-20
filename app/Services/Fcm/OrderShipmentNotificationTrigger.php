<?php

namespace App\Services\Fcm;

use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\Payment;
use App\Models\Notification;
use App\Services\Fcm\FcmNotificationService;

/**
 * Central place to dispatch FCM for order/payment/shipment lifecycle events.
 * Called from observer, webhook, admin order, and shipment services. Uses real system state.
 * Every notification is logged via NotificationDispatchService::sendSystemEvent.
 */
class OrderShipmentNotificationTrigger
{
    public const TYPE_ORDER_CREATED = 'order_created';
    public const TYPE_ORDER_STATUS = 'order_status';
    public const TYPE_PAYMENT_SUCCESS = 'payment_success';
    public const TYPE_PAYMENT_FAILED = 'payment_failed';
    public const TYPE_SHIPMENT_TRACKING = 'shipment_tracking';
    public const TYPE_SHIPMENT_DELIVERED = 'shipment_delivered';
    public const TYPE_REVIEW_COMPLETED = 'review_completed';

    public function __construct(
        protected NotificationDispatchService $dispatchService
    ) {}

    /**
     * Order created: notify order's user.
     */
    public function onOrderCreated(Order $order): void
    {
        $user = $order->user;
        if ($user === null) {
            return;
        }
        $title = __('notifications.order_created_title', [], 'en');
        $body = __('notifications.order_created_body', [], 'en');
        $this->dispatchService->sendSystemEvent(
            $title,
            $body,
            $user,
            FcmNotificationService::systemEventData(
                self::TYPE_ORDER_CREATED,
                (string) $order->id,
                $title,
                $body,
                'order',
                (string) $order->id,
                'order_details',
                ['order_id' => $order->id]
            ),
            (int) $order->id,
            null,
            ['event' => self::TYPE_ORDER_CREATED]
        );
    }

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
        $title = __('notifications.payment_success_title', [], 'en');
        $body = __('notifications.payment_success_body', ['order_number' => $order->order_number ?? $order->id], 'en');

        // B: persist in-app notification (so /notifications shows it).
        Notification::create([
            'user_id' => $user->id,
            'type' => 'payments',
            'title' => $title,
            'subtitle' => $body,
            'read' => false,
            'important' => true,
            'action_label' => 'View Details',
            'action_route' => '/order-detail/' . (string) $order->id,
        ]);

        $this->dispatchService->sendSystemEvent(
            $title,
            $body,
            $user,
            FcmNotificationService::systemEventData(
                self::TYPE_PAYMENT_SUCCESS,
                (string) $order->id,
                $title,
                $body,
                'order',
                (string) $order->id,
                'order_details',
                ['order_id' => $order->id]
            ),
            (int) $order->id,
            null,
            ['event' => self::TYPE_PAYMENT_SUCCESS, 'payment_id' => $payment->id]
        );
    }

    /**
     * Payment failed: notify order's user.
     */
    public function onPaymentFailed(Payment $payment): void
    {
        $order = $payment->order;
        if ($order === null) {
            return;
        }
        $user = $order->user;
        if ($user === null) {
            return;
        }
        $title = __('notifications.payment_failed_title', [], 'en');
        $body = __('notifications.payment_failed_body', ['order_number' => $order->order_number ?? $order->id], 'en');

        // B: persist in-app notification (so /notifications shows it).
        Notification::create([
            'user_id' => $user->id,
            'type' => 'payments',
            'title' => $title,
            'subtitle' => $body,
            'read' => false,
            'important' => true,
            'action_label' => 'View Details',
            'action_route' => '/order-detail/' . (string) $order->id,
        ]);

        $this->dispatchService->sendSystemEvent(
            $title,
            $body,
            $user,
            FcmNotificationService::systemEventData(
                self::TYPE_PAYMENT_FAILED,
                (string) $order->id,
                $title,
                $body,
                'order',
                (string) $order->id,
                'order_details',
                ['order_id' => $order->id]
            ),
            (int) $order->id,
            null,
            ['event' => self::TYPE_PAYMENT_FAILED, 'payment_id' => $payment->id]
        );
    }

    /**
     * Order moved to processing: notify order's user.
     */
    public function onOrderProcessing(Order $order): void
    {
        $this->notifyOrderStatus($order, Order::STATUS_PROCESSING,
            __('notifications.order_processing_title', [], 'en'),
            __('notifications.order_processing_body', [], 'en')
        );
    }

    /**
     * Order shipped: notify order's user.
     */
    public function onOrderShipped(Order $order): void
    {
        $this->notifyOrderStatus($order, 'shipped',
            __('notifications.order_shipped_title', [], 'en'),
            __('notifications.order_shipped_body', [], 'en')
        );
    }

    /**
     * Order in transit: notify order's user.
     */
    public function onOrderInTransit(Order $order): void
    {
        $this->notifyOrderStatus($order, Order::STATUS_IN_TRANSIT,
            __('notifications.order_in_transit_title', [], 'en'),
            __('notifications.order_in_transit_body', [], 'en')
        );
    }

    /**
     * Order delivered (order-level status): notify order's user.
     */
    public function onOrderDelivered(Order $order): void
    {
        $this->notifyOrderStatus($order, Order::STATUS_DELIVERED,
            __('notifications.order_delivered_title', [], 'en'),
            __('notifications.order_delivered_body', [], 'en')
        );
    }

    /**
     * Order cancelled: notify order's user.
     */
    public function onOrderCancelled(Order $order): void
    {
        $this->notifyOrderStatus($order, Order::STATUS_CANCELLED,
            __('notifications.order_cancelled_title', [], 'en'),
            __('notifications.order_cancelled_body', [], 'en')
        );
    }

    private function notifyOrderStatus(Order $order, string $status, string $title, string $body): void
    {
        $user = $order->user;
        if ($user === null) {
            return;
        }

        // B: persist in-app notification (so /notifications shows it).
        Notification::create([
            'user_id' => $user->id,
            'type' => 'orders',
            'title' => $title,
            'subtitle' => $body,
            'read' => false,
            'important' => in_array($status, [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED], true),
            'action_label' => 'View Details',
            'action_route' => '/order-detail/' . (string) $order->id,
        ]);

        $this->dispatchService->sendSystemEvent(
            $title,
            $body,
            $user,
            FcmNotificationService::systemEventData(
                self::TYPE_ORDER_STATUS,
                (string) $order->id,
                $title,
                $body,
                'order',
                (string) $order->id,
                'order_details',
                ['order_id' => $order->id, 'status' => $status]
            ),
            (int) $order->id,
            null,
            ['event' => self::TYPE_ORDER_STATUS, 'status' => $status]
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
        $title = __('notifications.shipment_tracking_title', [], 'en');
        $body = __('notifications.shipment_tracking_body', [
            'order_number' => $order->order_number ?? $order->id,
            'tracking' => $shipment->tracking_number ?? '',
        ], 'en');

        // B: persist in-app notification (so /notifications shows it).
        Notification::create([
            'user_id' => $user->id,
            'type' => 'shipments',
            'title' => $title,
            'subtitle' => $body,
            'read' => false,
            'important' => false,
            'action_label' => 'Track Order',
            'action_route' => '/order-tracking/' . (string) $order->id,
        ]);

        $this->dispatchService->sendSystemEvent(
            $title,
            $body,
            $user,
            FcmNotificationService::systemEventData(
                self::TYPE_SHIPMENT_TRACKING,
                (string) $shipment->id,
                $title,
                $body,
                'shipment',
                (string) $shipment->id,
                'order_details',
                ['order_id' => $order->id, 'shipment_id' => $shipment->id]
            ),
            (int) $order->id,
            (int) $shipment->id,
            ['event' => self::TYPE_SHIPMENT_TRACKING, 'tracking_number' => $shipment->tracking_number]
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
        $title = __('notifications.shipment_delivered_title', [], 'en');
        $body = __('notifications.shipment_delivered_body', ['order_number' => $order->order_number ?? $order->id], 'en');

        // B: persist in-app notification (so /notifications shows it).
        Notification::create([
            'user_id' => $user->id,
            'type' => 'shipments',
            'title' => $title,
            'subtitle' => $body,
            'read' => false,
            'important' => true,
            'action_label' => 'Track Order',
            'action_route' => '/order-tracking/' . (string) $order->id,
        ]);

        $this->dispatchService->sendSystemEvent(
            $title,
            $body,
            $user,
            FcmNotificationService::systemEventData(
                self::TYPE_SHIPMENT_DELIVERED,
                (string) $order->id,
                $title,
                $body,
                'order',
                (string) $order->id,
                'order_details',
                ['order_id' => $order->id]
            ),
            (int) $order->id,
            (int) $shipment->id,
            ['event' => self::TYPE_SHIPMENT_DELIVERED]
        );
    }

    /**
     * Order review completed: notify order's user.
     */
    public function onReviewCompleted(Order $order): void
    {
        $user = $order->user;
        if ($user === null) {
            return;
        }
        $title = __('notifications.review_completed_title', [], 'en');
        $body = __('notifications.review_completed_body', [], 'en');
        $this->dispatchService->sendSystemEvent(
            $title,
            $body,
            $user,
            FcmNotificationService::systemEventData(
                self::TYPE_REVIEW_COMPLETED,
                (string) $order->id,
                $title,
                $body,
                'order',
                (string) $order->id,
                'order_details',
                ['order_id' => $order->id]
            ),
            (int) $order->id,
            null,
            ['event' => self::TYPE_REVIEW_COMPLETED]
        );
    }
}
