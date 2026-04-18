<?php

namespace App\Services\PurchaseAssistant;

use App\Enums\Payment\PaymentStatus;
use App\Models\DraftOrder;
use App\Models\Order;
use App\Models\PurchaseAssistantRequest;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the converted PA order totals aligned with current admin_product_price / admin_service_fee
 * until the order leaves pending_payment (customer has not completed payment).
 */
class PurchaseAssistantOrderPricingSyncService
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Recalculate order/line snapshots from the request and cancel any pending gateway payments
     * so a new checkout session uses the updated amount.
     *
     * @return bool True if a sync was applied (totals or line data changed).
     */
    public function syncFromRequestIfEligible(PurchaseAssistantRequest $request): bool
    {
        if ($request->converted_order_id === null) {
            return false;
        }

        if ($request->admin_product_price === null || $request->admin_service_fee === null) {
            return false;
        }

        $order = Order::query()
            ->with(['shipments.lineItems', 'payments'])
            ->find($request->converted_order_id);

        if ($order === null || $order->status !== Order::STATUS_PENDING_PAYMENT) {
            return false;
        }

        if ((int) $order->purchase_assistant_request_id !== (int) $request->id) {
            return false;
        }

        $qty = max(1, (int) $request->quantity);
        $productSubtotal = round((float) $request->admin_product_price * $qty, 2);
        $serviceFee = round((float) $request->admin_service_fee, 2);
        $finalTotal = round($productSubtotal + $serviceFee, 2);

        $currentDue = (float) ($order->amount_due_now ?? 0);
        if ($currentDue <= 0) {
            $currentDue = (float) ($order->order_total_snapshot ?? $order->total_amount ?? 0);
        }

        $pendingPayments = $order->payments->filter(
            fn ($p) => $p->status === PaymentStatus::Pending
        );

        $totalsDiffer = abs($currentDue - $finalTotal) >= 0.005
            || abs((float) $order->total_amount - $finalTotal) >= 0.005
            || abs((float) ($order->service_fee_snapshot ?? 0) - $serviceFee) >= 0.005;

        $line = $order->shipments->first()?->lineItems->first();
        $linePricing = $line?->pricing_snapshot ?? [];
        $lineDiffers = $line === null
            || abs((float) ($linePricing['final_total'] ?? 0) - $finalTotal) >= 0.005;

        $pendingAmountStale = $pendingPayments->contains(
            fn ($p) => abs((float) $p->amount - $finalTotal) >= 0.01
        );

        if (! $totalsDiffer && ! $lineDiffers && ! $pendingAmountStale) {
            return false;
        }

        DB::transaction(function () use ($order, $request, $qty, $productSubtotal, $serviceFee, $finalTotal, $pendingPayments) {
            foreach ($pendingPayments as $payment) {
                $this->paymentService->markCancelled($payment, [
                    'reason' => 'purchase_assistant_pricing_updated',
                ]);
            }

            $order->update([
                'total_amount' => $finalTotal,
                'order_total_snapshot' => $finalTotal,
                'service_fee_snapshot' => $serviceFee,
                'shipping_total_snapshot' => 0,
                'amount_due_now' => $finalTotal,
                'wallet_applied_amount' => 0,
            ]);

            foreach ($order->shipments as $shipment) {
                $shipment->update([
                    'subtotal' => $productSubtotal,
                ]);

                foreach ($shipment->lineItems as $line) {
                    $ps = $line->product_snapshot ?? [];
                    $ps['unit_price'] = (float) $request->admin_product_price;
                    $prSnap = $line->pricing_snapshot ?? [];
                    $prSnap['subtotal'] = $productSubtotal;
                    $prSnap['service_fee'] = $serviceFee;
                    $prSnap['final_total'] = $finalTotal;
                    $prSnap['shipping_amount'] = (float) ($prSnap['shipping_amount'] ?? 0);

                    $line->update([
                        'price' => $finalTotal,
                        'quantity' => $qty,
                        'product_snapshot' => $ps,
                        'pricing_snapshot' => $prSnap,
                    ]);
                }
            }

            if ($order->draft_order_id) {
                DraftOrder::whereKey($order->draft_order_id)->update([
                    'subtotal_snapshot' => $productSubtotal,
                    'shipping_total_snapshot' => 0,
                    'service_fee_total_snapshot' => $serviceFee,
                    'final_total_snapshot' => $finalTotal,
                ]);
            }
        });

        return true;
    }
}
