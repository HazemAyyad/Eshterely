<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderLineItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
            'action' => 'nullable|string|in:mark_purchased,mark_in_transit',
            'store_tracking' => 'nullable|string|max:255',
            'purchase_notes' => 'nullable|string|max:2000',
        ]);

        $meta = $orderLineItem->review_metadata ?? [];
        if ($request->has('store_tracking')) {
            $meta['store_tracking'] = $validated['store_tracking'];
        }
        if ($request->has('purchase_notes')) {
            $meta['purchase_notes'] = $validated['purchase_notes'];
        }

        $status = (string) $orderLineItem->fulfillment_status;

        if (! empty($validated['action'])) {
            if ($validated['action'] === 'mark_purchased') {
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
            if ($validated['action'] === 'mark_in_transit') {
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
