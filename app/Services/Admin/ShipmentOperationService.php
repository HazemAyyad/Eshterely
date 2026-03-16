<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderOperationLog;
use App\Models\OrderShipment;
use App\Models\OrderShipmentEvent;
use App\Services\Fcm\OrderShipmentNotificationTrigger;
use Illuminate\Support\Facades\DB;

/**
 * Admin shipment operations: carrier, tracking, status, timeline events, delivered.
 * All actions are logged to order_operation_logs. Overrides remain traceable.
 */
class ShipmentOperationService
{
    public function __construct(
        protected OrderStatusWorkflowService $workflow,
        protected OrderShipmentNotificationTrigger $notificationTrigger
    ) {}

    /**
     * Gate for shipment operations. When implemented, review_state (needs_admin_review, needs_reprice) can block.
     */
    public function canOperateOnShipment(Order $order): bool
    {
        // Future: if (isset($order->review_state['needs_admin_review']) && $order->review_state['needs_admin_review']) return false;
        // Future: if (isset($order->review_state['needs_reprice']) && $order->review_state['needs_reprice']) return false;
        return true;
    }

    public function assignCarrier(OrderShipment $shipment, Admin $admin, string $carrier): OrderShipment
    {
        $this->ensureCanOperate($shipment->order, $admin);

        return DB::transaction(function () use ($shipment, $admin, $carrier) {
            $old = $shipment->carrier;
            $shipment->update(['carrier' => $carrier]);

            $this->log($shipment, $admin, OrderOperationLog::ACTION_SHIPMENT_CARRIER_CHANGED, [
                'carrier' => $carrier,
                'previous_carrier' => $old,
            ], 'Carrier assigned');

            return $shipment->fresh();
        });
    }

    public function assignTrackingNumber(OrderShipment $shipment, Admin $admin, string $trackingNumber): OrderShipment
    {
        $this->ensureCanOperate($shipment->order, $admin);

        return DB::transaction(function () use ($shipment, $admin, $trackingNumber) {
            $shipment->update(['tracking_number' => $trackingNumber]);

            $this->log($shipment, $admin, OrderOperationLog::ACTION_SHIPMENT_TRACKING_ASSIGNED, [
                'tracking_number' => $trackingNumber,
            ], 'Tracking number assigned');

            $shipment = $shipment->fresh(['order.user']);
            $this->notificationTrigger->onShipmentTrackingAssigned($shipment);
            return $shipment;
        });
    }

    public function updateShipmentStatus(OrderShipment $shipment, Admin $admin, string $status): OrderShipment
    {
        $this->ensureCanOperate($shipment->order, $admin);

        return DB::transaction(function () use ($shipment, $admin, $status) {
            $old = $shipment->shipment_status;
            $shipment->update(['shipment_status' => $status]);

            $this->log($shipment, $admin, OrderOperationLog::ACTION_SHIPMENT_STATUS_UPDATED, [
                'shipment_status' => $status,
                'previous_status' => $old,
            ], "Shipment status updated to {$status}");

            return $shipment->fresh();
        });
    }

    public function setEstimatedDelivery(OrderShipment $shipment, Admin $admin, ?\DateTimeInterface $at): OrderShipment
    {
        $this->ensureCanOperate($shipment->order, $admin);

        return DB::transaction(function () use ($shipment, $admin, $at) {
            $shipment->update(['estimated_delivery_at' => $at]);

            $this->log($shipment, $admin, OrderOperationLog::ACTION_SHIPMENT_ESTIMATED_DELIVERY_SET, [
                'estimated_delivery_at' => $at?->format('c'),
            ], $at ? 'Estimated delivery date set' : 'Estimated delivery date cleared');

            return $shipment->fresh();
        });
    }

    public function appendEvent(
        OrderShipment $shipment,
        Admin $admin,
        string $eventType,
        ?string $eventLabel = null,
        ?\DateTimeInterface $eventTime = null,
        ?string $location = null,
        ?array $payload = null,
        ?string $notes = null
    ): OrderShipmentEvent {
        $this->ensureCanOperate($shipment->order, $admin);

        return DB::transaction(function () use ($shipment, $admin, $eventType, $eventLabel, $eventTime, $location, $payload, $notes) {
            $event = $shipment->events()->create([
                'event_type' => $eventType,
                'event_label' => $eventLabel ?? $eventType,
                'event_time' => $eventTime ?? now(),
                'location' => $location,
                'payload' => $payload,
                'notes' => $notes,
            ]);

            $this->log($shipment, $admin, OrderOperationLog::ACTION_SHIPMENT_EVENT_ADDED, [
                'order_shipment_event_id' => $event->id,
                'event_type' => $eventType,
                'event_label' => $event->event_label,
            ], "Timeline event: {$eventType}");

            if ($eventType === OrderShipmentEvent::TYPE_DELIVERED) {
                $this->markShipmentDeliveredAt($shipment, $event->event_time ?? now());
                $shipmentRefreshed = $shipment->fresh(['order.user']);
                $this->notificationTrigger->onShipmentDelivered($shipmentRefreshed);
            }

            return $event;
        });
    }

    public function markDelivered(OrderShipment $shipment, Admin $admin): OrderShipment
    {
        $this->ensureCanOperate($shipment->order, $admin);

        return DB::transaction(function () use ($shipment, $admin) {
            $this->markShipmentDeliveredAt($shipment, now());

            $shipment->events()->create([
                'event_type' => OrderShipmentEvent::TYPE_DELIVERED,
                'event_label' => 'Delivered',
                'event_time' => now(),
            ]);

            $this->log($shipment, $admin, OrderOperationLog::ACTION_SHIPMENT_DELIVERED, [
                'order_shipment_id' => $shipment->id,
            ], 'Shipment marked delivered');

            $shipment = $shipment->fresh(['order.user']);
            $this->notificationTrigger->onShipmentDelivered($shipment);
            return $shipment;
        });
    }

    private function markShipmentDeliveredAt(OrderShipment $shipment, \DateTimeInterface $at): void
    {
        $shipment->update([
            'shipment_status' => 'delivered',
            'delivered_at' => $at,
        ]);

        $order = $shipment->order;
        $allDelivered = $order->shipments()->whereNull('delivered_at')->where('id', '!=', $shipment->id)->doesntExist();

        if ($allDelivered) {
            $check = $this->workflow->canTransitionTo($order, Order::STATUS_DELIVERED);
            if ($check['allowed']) {
                $order->update([
                    'status' => Order::STATUS_DELIVERED,
                    'delivered_at' => $at,
                ]);
            }
        }
    }

    private function ensureCanOperate(Order $order, Admin $admin): void
    {
        if (! $this->canOperateOnShipment($order)) {
            throw new \InvalidArgumentException('Order is not eligible for shipment operations (review state).');
        }
    }

    private function log(OrderShipment $shipment, Admin $admin, string $action, array $payload = [], ?string $notes = null): OrderOperationLog
    {
        $payload['order_shipment_id'] = $shipment->id;
        return $shipment->order->operationLogs()->create([
            'admin_id' => $admin->id,
            'action' => $action,
            'payload' => $payload,
            'notes' => $notes,
        ]);
    }
}
