<?php

namespace App\Services\PurchaseAssistant;

use App\Models\Order;
use App\Models\PurchaseAssistantRequest;
use App\Models\User;

/**
 * Keeps Purchase Assistant request status aligned with order payment state.
 */
class PurchaseAssistantRequestStatusSync
{
    public function __construct(
        protected PurchaseAssistantRequestNotifier $notifier
    ) {}

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
