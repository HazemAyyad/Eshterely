<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderLineItem;
use App\Models\WarehouseReceipt;
use App\Support\AdminOrderLineItemDisplay;
use App\Support\AdminWarehouseReceiptImages;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseReceivingController extends Controller
{
    /**
     * POST /admin/warehouse/order-line-items/{orderLineItem}/receive
     */
    public function store(Request $request, OrderLineItem $orderLineItem): JsonResponse|RedirectResponse
    {
        if (! AdminOrderLineItemDisplay::canReceiveIntoWarehouse($orderLineItem)) {
            $msg = __('admin.warehouse_receive_not_eligible');
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->back()->with('error', $msg);
        }

        $validated = $request->validate([
            'received_at' => 'nullable|date',
            'received_weight' => 'nullable|numeric|min:0',
            'received_length' => 'nullable|numeric|min:0',
            'received_width' => 'nullable|numeric|min:0',
            'received_height' => 'nullable|numeric|min:0',
            'receipt_images' => 'nullable|array',
            'receipt_images.*' => 'image|max:10240',
            'images_text' => 'nullable|string|max:10000',
            'condition_notes' => 'nullable|string|max:2000',
            'special_handling_type' => 'nullable|string|max:50',
            'additional_fee_amount' => 'nullable|numeric|min:0',
        ]);

        $images = AdminWarehouseReceiptImages::collectFromRequest($request);

        DB::transaction(function () use ($request, $orderLineItem, $validated, $images) {
            WarehouseReceipt::create([
                'order_line_item_id' => $orderLineItem->id,
                'received_at' => isset($validated['received_at']) ? Carbon::parse($validated['received_at']) : now(),
                'received_weight' => $validated['received_weight'] ?? null,
                'received_length' => $validated['received_length'] ?? null,
                'received_width' => $validated['received_width'] ?? null,
                'received_height' => $validated['received_height'] ?? null,
                'images' => $images,
                'condition_notes' => $validated['condition_notes'] ?? null,
                'special_handling_type' => $validated['special_handling_type'] ?? null,
                'additional_fee_amount' => round((float) ($validated['additional_fee_amount'] ?? 0), 2),
                'created_by' => $request->user('admin')?->id,
            ]);

            $orderLineItem->update([
                'fulfillment_status' => OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE,
            ]);
        });

        $payload = [
            'success' => true,
            'message' => __('admin.success'),
            'order_line_item_id' => (string) $orderLineItem->id,
            'fulfillment_status' => OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE,
        ];

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($payload);
        }

        return redirect()
            ->route('admin.warehouse.index')
            ->with('success', __('admin.warehouse_item_received'));
    }

    /**
     * PUT — update the latest warehouse receipt for an already-received line (intake correction).
     */
    public function update(Request $request, OrderLineItem $orderLineItem, WarehouseReceipt $warehouseReceipt): JsonResponse|RedirectResponse
    {
        abort_unless((int) $warehouseReceipt->order_line_item_id === (int) $orderLineItem->id, 404);

        if ($orderLineItem->fulfillment_status !== OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE) {
            $msg = __('admin.warehouse_receive_update_not_arrived');
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->back()->with('error', $msg);
        }

        $latestId = WarehouseReceipt::query()
            ->where('order_line_item_id', $orderLineItem->id)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->value('id');

        if ((int) $warehouseReceipt->id !== (int) $latestId) {
            $msg = __('admin.warehouse_receive_update_not_latest');
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->back()->with('error', $msg);
        }

        $validated = $request->validate([
            'received_at' => 'nullable|date',
            'received_weight' => 'nullable|numeric|min:0',
            'received_length' => 'nullable|numeric|min:0',
            'received_width' => 'nullable|numeric|min:0',
            'received_height' => 'nullable|numeric|min:0',
            'receipt_images' => 'nullable|array',
            'receipt_images.*' => 'image|max:10240',
            'images_text' => 'nullable|string|max:10000',
            'condition_notes' => 'nullable|string|max:2000',
            'special_handling_type' => 'nullable|string|max:50',
            'additional_fee_amount' => 'nullable|numeric|min:0',
            'retained_image_urls' => 'nullable|array',
            'retained_image_urls.*' => 'nullable|string|max:2000',
        ]);

        $originalImages = is_array($warehouseReceipt->images) ? $warehouseReceipt->images : [];
        $images = AdminWarehouseReceiptImages::mergeRetainedWithNewUploads($request, $originalImages);

        DB::transaction(function () use ($warehouseReceipt, $validated, $images) {
            $warehouseReceipt->update([
                'received_at' => isset($validated['received_at']) ? Carbon::parse($validated['received_at']) : $warehouseReceipt->received_at,
                'received_weight' => $validated['received_weight'] ?? null,
                'received_length' => $validated['received_length'] ?? null,
                'received_width' => $validated['received_width'] ?? null,
                'received_height' => $validated['received_height'] ?? null,
                'images' => $images,
                'condition_notes' => $validated['condition_notes'] ?? null,
                'special_handling_type' => $validated['special_handling_type'] ?? null,
                'additional_fee_amount' => round((float) ($validated['additional_fee_amount'] ?? 0), 2),
            ]);
        });

        $payload = [
            'success' => true,
            'message' => __('admin.success'),
            'warehouse_receipt_id' => (string) $warehouseReceipt->id,
        ];

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($payload);
        }

        return redirect()
            ->route('admin.warehouse.index')
            ->with('success', __('admin.success'));
    }
}
