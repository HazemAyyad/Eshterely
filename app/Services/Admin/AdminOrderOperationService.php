<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderOperationLog;
use App\Models\OrderShipment;
use App\Services\Fcm\OrderShipmentNotificationTrigger;
use Illuminate\Support\Facades\DB;

/**
 * Admin order operations: review, status change, shipping override. All actions are logged.
 */
class AdminOrderOperationService
{
    public function __construct(
        protected OrderStatusWorkflowService $workflow,
        protected OrderShipmentNotificationTrigger $notificationTrigger
    ) {}

    /**
     * Mark order as reviewed and optionally set admin notes / review_state.
     */
    public function markAsReviewed(Order $order, Admin $admin, ?string $notes = null, array $reviewState = []): Order
    {
        return DB::transaction(function () use ($order, $admin, $notes, $reviewState) {
            $updates = [
                'needs_review' => false,
                'reviewed_at' => now(),
            ];
            if ($notes !== null) {
                $updates['admin_notes'] = $notes;
            }
            if ($reviewState !== []) {
                $updates['review_state'] = array_merge($order->review_state ?? [], $reviewState);
            }
            $order->update($updates);

            $this->log($order, $admin, OrderOperationLog::ACTION_REVIEW, [
                'notes' => $notes,
                'review_state' => $reviewState,
            ], 'Order marked as reviewed');

            $order = $order->fresh();
            $this->notificationTrigger->onReviewCompleted($order);
            return $order;
        });
    }

    /**
     * Change order status with workflow validation. Logged.
     */
    public function updateStatus(Order $order, Admin $admin, string $newStatus): Order
    {
        $check = $this->workflow->canTransitionTo($order, $newStatus);
        if (! $check['allowed']) {
            throw new \InvalidArgumentException($check['reason']);
        }

        return DB::transaction(function () use ($order, $admin, $newStatus) {
            $oldStatus = $order->status;
            $updates = ['status' => $newStatus];
            if ($newStatus === Order::STATUS_DELIVERED) {
                $updates['delivered_at'] = now();
            }
            $order->update($updates);

            $this->log($order, $admin, OrderOperationLog::ACTION_STATUS_CHANGE, [
                'from' => $oldStatus,
                'to' => $newStatus,
            ], "Status changed from {$oldStatus} to {$newStatus}");

            $order = $order->fresh();
            if ($newStatus === Order::STATUS_PROCESSING) {
                $this->notificationTrigger->onOrderProcessing($order);
            } elseif ($newStatus === Order::STATUS_SHIPPED_TO_WAREHOUSE || $newStatus === Order::STATUS_INTERNATIONAL_SHIPPING) {
                $this->notificationTrigger->onOrderShipped($order);
            } elseif ($newStatus === Order::STATUS_IN_TRANSIT) {
                $this->notificationTrigger->onOrderInTransit($order);
            } elseif ($newStatus === Order::STATUS_DELIVERED) {
                $this->notificationTrigger->onOrderDelivered($order);
            } elseif ($newStatus === Order::STATUS_CANCELLED) {
                $this->notificationTrigger->onOrderCancelled($order);
            }
            return $order;
        });
    }

    /**
     * Apply shipping override to a shipment. Records override separately from original snapshot; logs action.
     */
    public function applyShippingOverride(
        OrderShipment $shipment,
        Admin $admin,
        ?float $amount = null,
        ?string $carrier = null,
        ?string $notes = null
    ): OrderShipment {
        return DB::transaction(function () use ($shipment, $admin, $amount, $carrier, $notes) {
            $updates = [
                'shipping_override_at' => now(),
                'shipping_override_notes' => $notes,
            ];
            if ($amount !== null) {
                $updates['shipping_override_amount'] = $amount;
            }
            if ($carrier !== null) {
                $updates['shipping_override_carrier'] = $carrier;
            }
            $shipment->update($updates);

            $this->log($shipment->order, $admin, OrderOperationLog::ACTION_SHIPPING_OVERRIDE, [
                'order_shipment_id' => $shipment->id,
                'shipping_override_amount' => $amount,
                'shipping_override_carrier' => $carrier,
                'notes' => $notes,
            ], 'Shipping override applied');

            return $shipment->fresh();
        });
    }

    /**
     * Add a reprice/override note (audit only; does not change totals).
     */
    public function addRepriceNote(Order $order, Admin $admin, string $notes): OrderOperationLog
    {
        return $this->log($order, $admin, OrderOperationLog::ACTION_REPRICE_NOTE, [], $notes);
    }

    protected function log(Order $order, ?Admin $admin, string $action, array $payload = [], ?string $notes = null): OrderOperationLog
    {
        return $order->operationLogs()->create([
            'admin_id' => $admin?->id,
            'action' => $action,
            'payload' => $payload ?: null,
            'notes' => $notes,
        ]);
    }
}
