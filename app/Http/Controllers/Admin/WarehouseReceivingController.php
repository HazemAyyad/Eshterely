<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderLineItem;
use App\Models\WarehouseReceipt;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseReceivingController extends Controller
{
    /**
     * POST /admin/warehouse/order-line-items/{orderLineItem}/receive
     */
    public function store(Request $request, OrderLineItem $orderLineItem): JsonResponse
    {
        $validated = $request->validate([
            'received_at' => 'nullable|date',
            'received_weight' => 'nullable|numeric|min:0',
            'received_length' => 'nullable|numeric|min:0',
            'received_width' => 'nullable|numeric|min:0',
            'received_height' => 'nullable|numeric|min:0',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'condition_notes' => 'nullable|string|max:2000',
            'special_handling_type' => 'nullable|string|max:50',
            'additional_fee_amount' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $orderLineItem, $validated) {
            WarehouseReceipt::create([
                'order_line_item_id' => $orderLineItem->id,
                'received_at' => isset($validated['received_at']) ? Carbon::parse($validated['received_at']) : now(),
                'received_weight' => $validated['received_weight'] ?? null,
                'received_length' => $validated['received_length'] ?? null,
                'received_width' => $validated['received_width'] ?? null,
                'received_height' => $validated['received_height'] ?? null,
                'images' => $validated['images'] ?? [],
                'condition_notes' => $validated['condition_notes'] ?? null,
                'special_handling_type' => $validated['special_handling_type'] ?? null,
                'additional_fee_amount' => round((float) ($validated['additional_fee_amount'] ?? 0), 2),
                'created_by' => $request->user('admin')?->id,
            ]);

            $orderLineItem->update([
                'fulfillment_status' => OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE,
            ]);
        });

        return response()->json([
            'success' => true,
            'order_line_item_id' => (string) $orderLineItem->id,
            'fulfillment_status' => OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE,
        ]);
    }
}
