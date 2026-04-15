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
}
