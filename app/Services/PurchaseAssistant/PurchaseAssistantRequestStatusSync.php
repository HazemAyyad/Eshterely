<?php

namespace App\Services\PurchaseAssistant;

use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\PurchaseAssistantRequest;
use App\Models\User;

/**
 * Keeps Purchase Assistant request status aligned with order payment state
 * and (for PA orders) linked order line fulfillment.
 */
class PurchaseAssistantRequestStatusSync
{
    public function __construct(
        protected PurchaseAssistantRequestNotifier $notifier
    ) {}

    /**
     * When admin (or automated flows) update the PA-linked order line fulfillment, mirror the stage on the PA request.
     */
    public function syncFromOrderLineItem(OrderLineItem $lineItem): void
    {
        $lineItem->loadMissing('shipment.order');
        $order = $lineItem->shipment?->order;
        if ($order === null || $order->purchase_assistant_request_id === null) {
            return;
        }

        if (! $this->lineItemBelongsToPurchaseAssistantOrder($lineItem, $order)) {
            return;
        }

        $pa = PurchaseAssistantRequest::find($order->purchase_assistant_request_id);
        if ($pa === null) {
            return;
        }

        if ($this->isPaymentPhasePurchaseAssistantStatus($pa->status)) {
            return;
        }

        if (in_array($pa->status, [
            PurchaseAssistantRequest::STATUS_COMPLETED,
            PurchaseAssistantRequest::STATUS_REJECTED,
            PurchaseAssistantRequest::STATUS_CANCELLED,
        ], true)) {
            return;
        }

        $target = $this->mapLineFulfillmentToPurchaseAssistantStatus($lineItem->fulfillment_status);
        if ($target === null || $target === $pa->status) {
            return;
        }

        $old = $pa->status;
        $pa->update(['status' => $target]);
        $pa->refresh();

        $user = User::find($pa->user_id);
        if ($user !== null) {
            $this->notifier->notifyAfterStatusChange($pa, $user, $old, $pa->status);
        }
    }

    private function lineItemBelongsToPurchaseAssistantOrder(OrderLineItem $lineItem, Order $order): bool
    {
        $rid = $lineItem->product_snapshot['purchase_assistant_request_id'] ?? null;
        if ($rid !== null && (int) $rid === (int) $order->purchase_assistant_request_id) {
            return true;
        }

        $badges = $lineItem->badges ?? [];

        return in_array('purchase_assistant', $badges, true);
    }

    private function isPaymentPhasePurchaseAssistantStatus(string $status): bool
    {
        return in_array($status, [
            PurchaseAssistantRequest::STATUS_SUBMITTED,
            PurchaseAssistantRequest::STATUS_UNDER_REVIEW,
            PurchaseAssistantRequest::STATUS_AWAITING_CUSTOMER_PAYMENT,
            PurchaseAssistantRequest::STATUS_PAYMENT_UNDER_REVIEW,
        ], true);
    }

    private function mapLineFulfillmentToPurchaseAssistantStatus(?string $fulfillment): ?string
    {
        if ($fulfillment === null || $fulfillment === '') {
            return null;
        }

        return match ($fulfillment) {
            OrderLineItem::FULFILLMENT_PAID,
            OrderLineItem::FULFILLMENT_REVIEWED => PurchaseAssistantRequest::STATUS_PURCHASING,
            OrderLineItem::FULFILLMENT_PURCHASED => PurchaseAssistantRequest::STATUS_PURCHASED,
            OrderLineItem::FULFILLMENT_IN_TRANSIT_TO_WAREHOUSE => PurchaseAssistantRequest::STATUS_IN_TRANSIT_TO_WAREHOUSE,
            OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE,
            OrderLineItem::FULFILLMENT_READY_FOR_SHIPMENT => PurchaseAssistantRequest::STATUS_RECEIVED_AT_WAREHOUSE,
            default => null,
        };
    }

    /**
     * Customer started a hosted card checkout (session created). Moves PA out of awaiting payment.
     */
    public function onCustomerInitiatedGatewayPayment(Order $order): void
    {
        $this->transitionPaymentLifecycle(
            $order,
            PurchaseAssistantRequest::STATUS_PAYMENT_UNDER_REVIEW,
            [
                PurchaseAssistantRequest::STATUS_AWAITING_CUSTOMER_PAYMENT,
            ]
        );
    }

    /**
     * Gateway reported payment processing / pending capture.
     */
    public function onOrderPaymentProcessing(Order $order): void
    {
        $this->transitionPaymentLifecycle(
            $order,
            PurchaseAssistantRequest::STATUS_PAYMENT_UNDER_REVIEW,
            [
                PurchaseAssistantRequest::STATUS_AWAITING_CUSTOMER_PAYMENT,
                PurchaseAssistantRequest::STATUS_PAYMENT_UNDER_REVIEW,
            ]
        );
    }

    /**
     * Linked order status or execution changed in admin — keep PA aligned when order is paid.
     */
    public function syncFromLinkedOrder(Order $order): void
    {
        if ($order->purchase_assistant_request_id === null) {
            return;
        }

        if ($order->status !== Order::STATUS_PAID) {
            return;
        }

        $this->onOrderMarkedPaid($order);
    }

    public function onOrderMarkedPaid(Order $order): void
    {
        if ($order->purchase_assistant_request_id === null) {
            return;
        }

        $request = PurchaseAssistantRequest::find($order->purchase_assistant_request_id);
        if ($request === null) {
            return;
        }

        $oldStatus = $request->status;

        if ($request->status === PurchaseAssistantRequest::STATUS_AWAITING_CUSTOMER_PAYMENT
            || $request->status === PurchaseAssistantRequest::STATUS_PAYMENT_UNDER_REVIEW) {
            $request->update([
                'status' => PurchaseAssistantRequest::STATUS_PAID,
            ]);
        }

        $request->refresh();
        if ($oldStatus !== PurchaseAssistantRequest::STATUS_PAID
            && $request->status === PurchaseAssistantRequest::STATUS_PAID) {
            $user = User::find($request->user_id);
            if ($user !== null) {
                $this->notifier->notifyAfterStatusChange($request, $user, $oldStatus, $request->status);
            }
        }
    }

    /**
     * @param  list<string>  $allowedFrom
     */
    private function transitionPaymentLifecycle(Order $order, string $targetStatus, array $allowedFrom): void
    {
        if ($order->purchase_assistant_request_id === null) {
            return;
        }

        $request = PurchaseAssistantRequest::find($order->purchase_assistant_request_id);
        if ($request === null) {
            return;
        }

        if (! in_array($request->status, $allowedFrom, true)) {
            return;
        }

        $oldStatus = $request->status;
        if ($oldStatus === $targetStatus) {
            return;
        }

        $request->update(['status' => $targetStatus]);
        $request->refresh();

        $user = User::find($request->user_id);
        if ($user !== null) {
            $this->notifier->notifyAfterStatusChange($request, $user, $oldStatus, $request->status);
        }
    }
}
