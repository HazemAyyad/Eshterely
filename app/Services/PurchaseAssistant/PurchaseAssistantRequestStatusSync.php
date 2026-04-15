<?php

namespace App\Services\PurchaseAssistant;

use App\Models\Order;
use App\Models\PurchaseAssistantRequest;

/**
 * Keeps Purchase Assistant request status aligned with order payment state.
 */
class PurchaseAssistantRequestStatusSync
{
    public function onOrderMarkedPaid(Order $order): void
    {
        if ($order->purchase_assistant_request_id === null) {
            return;
        }

        $request = PurchaseAssistantRequest::find($order->purchase_assistant_request_id);
        if ($request === null) {
            return;
        }

        if ($request->status === PurchaseAssistantRequest::STATUS_AWAITING_CUSTOMER_PAYMENT
            || $request->status === PurchaseAssistantRequest::STATUS_PAYMENT_UNDER_REVIEW) {
            $request->update([
                'status' => PurchaseAssistantRequest::STATUS_PAID,
            ]);
        }
    }
}
