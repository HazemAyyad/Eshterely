<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderLineItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * User warehouse: items arrived and ready to combine into outbound shipments.
 */
class WarehouseController extends Controller
{
    /**
     * GET /api/warehouse/items
     */
    public function items(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $items = OrderLineItem::query()
            ->select('order_line_items.*')
            ->join('order_shipments', 'order_line_items.order_shipment_id', '=', 'order_shipments.id')
            ->join('orders', 'order_shipments.order_id', '=', 'orders.id')
            ->where('orders.user_id', $userId)
            ->where('order_line_items.fulfillment_status', OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE)
            ->whereDoesntHave('shipmentItems')
            ->with(['latestWarehouseReceipt'])
            ->orderBy('order_line_items.id')
            ->get();

        return response()->json([
            'items' => $items->map(fn (OrderLineItem $line) => $this->serializeWarehouseItem($line)),
        ]);
    }

    private function serializeWarehouseItem(OrderLineItem $line): array
    {
        $r = $line->latestWarehouseReceipt;

        return [
            'id' => (string) $line->id,
            'name' => $line->name,
            'image_url' => $line->image_url,
            'quantity' => $line->quantity,
            'price' => (float) $line->price,
            'weight_kg' => $line->weight_kg !== null ? (float) $line->weight_kg : null,
            'dimensions' => $line->dimensions,
            'receipt' => $r ? [
                'received_at' => $r->received_at?->toIso8601String(),
                'received_weight' => $r->received_weight !== null ? (float) $r->received_weight : null,
                'received_length' => $r->received_length !== null ? (float) $r->received_length : null,
                'received_width' => $r->received_width !== null ? (float) $r->received_width : null,
                'received_height' => $r->received_height !== null ? (float) $r->received_height : null,
                'images' => $r->images ?? [],
                'condition_notes' => $r->condition_notes,
                'special_handling_type' => $r->special_handling_type,
                'additional_fee_amount' => round((float) $r->additional_fee_amount, 2),
            ] : null,
        ];
    }
}
