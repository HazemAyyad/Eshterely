<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Admin actions for user outbound shipments (second payment flow).
 */
class OutboundShipmentController extends Controller
{
    /**
     * POST /admin/outbound-shipments/{shipment}/pack
     */
    public function pack(Request $request, Shipment $shipment): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'final_weight' => 'required|numeric|min:0',
            'final_length' => 'required|numeric|min:0',
            'final_width' => 'required|numeric|min:0',
            'final_height' => 'required|numeric|min:0',
            'final_box_image' => 'nullable|image|max:12288',
        ]);

        $imagePath = $shipment->final_box_image;
        if ($request->hasFile('final_box_image')) {
            $imagePath = Storage::url($request->file('final_box_image')->store('shipment-pack', 'public'));
        }

        if (! in_array($shipment->status, [Shipment::STATUS_PAID, Shipment::STATUS_PACKED], true)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'Shipment must be paid before packing.', 'status' => 422], 422);
            }
            return redirect()->back()->with('error', 'Shipment must be paid before packing.');
        }

        $shipment->update([
            'final_weight' => $validated['final_weight'],
            'final_length' => $validated['final_length'],
            'final_width' => $validated['final_width'],
            'final_height' => $validated['final_height'],
            'final_box_image' => $imagePath,
            'status' => Shipment::STATUS_PACKED,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('admin.success'),
                'shipment' => $this->serialize($shipment->fresh()),
            ]);
        }

        return redirect()
            ->route('admin.shipments.show', $shipment)
            ->with('success', __('admin.shipment_packed'));
    }

    /**
     * POST /admin/outbound-shipments/{shipment}/ship
     */
    public function ship(Request $request, Shipment $shipment): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'carrier' => 'required|string|max:80',
            'tracking_number' => 'required|string|max:255',
            'dispatch_note' => 'nullable|string|max:1000',
        ]);

        if ($shipment->status !== Shipment::STATUS_PACKED) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'Shipment must be packed before dispatch.', 'status' => 422], 422);
            }
            return redirect()->back()->with('error', 'Shipment must be packed before dispatch.');
        }

        $breakdown = $shipment->pricing_breakdown ?? [];
        if (array_key_exists('dispatch_note', $validated) && $validated['dispatch_note'] !== null && $validated['dispatch_note'] !== '') {
            $breakdown['admin_dispatch_note'] = $validated['dispatch_note'];
        }

        $shipment->update([
            'carrier' => $validated['carrier'],
            'tracking_number' => $validated['tracking_number'],
            'status' => Shipment::STATUS_SHIPPED,
            'dispatched_at' => now(),
            'pricing_breakdown' => $breakdown,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('admin.success'),
                'shipment' => $this->serialize($shipment->fresh()),
            ]);
        }

        return redirect()
            ->route('admin.shipments.show', $shipment)
            ->with('success', __('admin.shipment_dispatched'));
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
