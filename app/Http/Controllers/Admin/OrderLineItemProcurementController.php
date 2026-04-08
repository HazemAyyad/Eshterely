<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderLineItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderLineItemProcurementController extends Controller
{
    /**
     * Procurement updates for a single order line (purchase flow — not warehouse receive).
     */
    public function update(Request $request, OrderLineItem $orderLineItem): RedirectResponse|JsonResponse
    {
        $orderLineItem->load('shipment.order.payments');
        abort_unless($orderLineItem->shipment && $orderLineItem->shipment->order, 404);

        $order = $orderLineItem->shipment->order;
        $hasPaid = $order->payments->contains(fn ($p) => $p->status->value === 'paid');
        if (! $hasPaid && $order->status !== \App\Models\Order::STATUS_PAID) {
            $msg = 'Order must be paid before procurement updates.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->back()->with('error', $msg);
        }

        $validated = $request->validate([
            'procurement_action' => 'nullable|string|in:mark_purchased,mark_in_transit',
            'action' => 'nullable|string|in:mark_purchased,mark_in_transit',
            'store_tracking' => 'nullable|string|max:255',
            'purchase_notes' => 'nullable|string|max:2000',
            'purchase_details' => 'nullable|string|max:5000',
            'actual_purchase_price' => 'nullable|numeric|min:0',
            'assigned_buyer' => 'nullable|string|max:120',
        ]);

        $procurementAction = $validated['procurement_action'] ?? $validated['action'] ?? null;

        $meta = $orderLineItem->review_metadata ?? [];
        foreach (['store_tracking', 'purchase_notes', 'purchase_details', 'assigned_buyer'] as $key) {
            if (array_key_exists($key, $validated)) {
                $meta[$key] = $validated[$key];
            }
        }
        if ($request->has('actual_purchase_price')) {
            $v = $request->input('actual_purchase_price');
            $meta['actual_purchase_price'] = ($v === null || $v === '') ? null : round((float) $v, 2);
        }

        $status = (string) $orderLineItem->fulfillment_status;

        if (! empty($procurementAction)) {
            if ($procurementAction === 'mark_purchased') {
                if (! in_array($status, [
                    OrderLineItem::FULFILLMENT_PAID,
                    OrderLineItem::FULFILLMENT_REVIEWED,
                ], true)) {
                    $msg = 'Mark purchased is only available for paid line items.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => $msg], 422);
                    }

                    return redirect()->back()->with('error', $msg);
                }
                $status = OrderLineItem::FULFILLMENT_PURCHASED;
            }
            if ($procurementAction === 'mark_in_transit') {
                if (! in_array($status, [
                    OrderLineItem::FULFILLMENT_PAID,
                    OrderLineItem::FULFILLMENT_REVIEWED,
                    OrderLineItem::FULFILLMENT_PURCHASED,
                ], true)) {
                    $msg = 'In transit can be set from paid or purchased.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => $msg], 422);
                    }

                    return redirect()->back()->with('error', $msg);
                }
                $status = OrderLineItem::FULFILLMENT_IN_TRANSIT_TO_WAREHOUSE;
            }
        }

        $orderLineItem->update([
            'fulfillment_status' => $status,
            'review_metadata' => $meta,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }

        return redirect()->back()->with('success', __('admin.success'));
    }
}
