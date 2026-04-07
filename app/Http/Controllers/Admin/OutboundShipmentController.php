<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin actions for user outbound shipments (second payment flow).
 */
class OutboundShipmentController extends Controller
{
    /**
     * POST /admin/outbound-shipments/{shipment}/pack
     */
    public function pack(Request $request, Shipment $shipment): JsonResponse
    {
        $validated = $request->validate([
            'final_weight' => 'required|numeric|min:0',
            'final_length' => 'required|numeric|min:0',
            'final_width' => 'required|numeric|min:0',
            'final_height' => 'required|numeric|min:0',
            'final_box_image' => 'nullable|string|max:2000',
        ]);

        if (! in_array($shipment->status, [Shipment::STATUS_PAID, Shipment::STATUS_PACKED], true)) {
            return response()->json(['message' => 'Shipment must be paid before packing.', 'status' => 422], 422);
        }

        $shipment->update([
            'final_weight' => $validated['final_weight'],
            'final_length' => $validated['final_length'],
            'final_width' => $validated['final_width'],
            'final_height' => $validated['final_height'],
            'final_box_image' => $validated['final_box_image'] ?? null,
            'status' => Shipment::STATUS_PACKED,
        ]);

        return response()->json(['success' => true, 'shipment' => $this->serialize($shipment->fresh())]);
    }

    /**
     * POST /admin/outbound-shipments/{shipment}/ship
     */
    public function ship(Request $request, Shipment $shipment): JsonResponse
    {
        $validated = $request->validate([
            'carrier' => 'required|string|max:80',
            'tracking_number' => 'required|string|max:255',
        ]);

        if ($shipment->status !== Shipment::STATUS_PACKED) {
            return response()->json(['message' => 'Shipment must be packed before dispatch.', 'status' => 422], 422);
        }

        $shipment->update([
            'carrier' => $validated['carrier'],
            'tracking_number' => $validated['tracking_number'],
            'status' => Shipment::STATUS_SHIPPED,
            'dispatched_at' => now(),
        ]);

        return response()->json(['success' => true, 'shipment' => $this->serialize($shipment->fresh())]);
    }

    private function serialize(Shipment $s): array
    {
        return [
            'id' => (string) $s->id,
            'status' => $s->status,
            'carrier' => $s->carrier,
            'tracking_number' => $s->tracking_number,
            'final_box_image' => $s->final_box_image,
            'dispatched_at' => $s->dispatched_at?->toIso8601String(),
        ];
    }
}
