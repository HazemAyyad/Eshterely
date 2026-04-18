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

        if ($shipment->status !== Shipment::STATUS_PAID) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'Only a paid shipment that is not yet packed can be packed.', 'status' => 422], 422);
            }

            return redirect()->back()->with('error', 'Only a paid shipment that is not yet packed can be packed.');
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

    /**
     * POST /admin/shipments/{shipment}/mark-delivered — admin fallback when customer has not confirmed.
     */
    public function markDelivered(Request $request, Shipment $shipment): JsonResponse|RedirectResponse
    {
        if ($shipment->status !== Shipment::STATUS_SHIPPED) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'Only a shipped shipment can be marked delivered.', 'status' => 422], 422);
            }

            return redirect()->back()->with('error', 'Only a shipped shipment can be marked delivered.');
        }

        $breakdown = $shipment->pricing_breakdown ?? [];
        $cd = is_array($breakdown['customer_delivery'] ?? null) ? $breakdown['customer_delivery'] : [];
        $cd['confirmed_at'] = now()->toIso8601String();
        $cd['source'] = 'admin';
        $breakdown['customer_delivery'] = $cd;

        $shipment->update([
            'status' => Shipment::STATUS_DELIVERED,
            'delivered_at' => now(),
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
            ->with('success', __('admin.shipment_marked_delivered'));
    }

    private function serialize(Shipment $s): array
    {
        $breakdown = $s->pricing_breakdown ?? [];
        $cd = is_array($breakdown['customer_delivery'] ?? null) ? $breakdown['customer_delivery'] : [];

        return [
            'id' => (string) $s->id,
            'status' => $s->status,
            'carrier' => $s->carrier,
            'tracking_number' => $s->tracking_number,
            'final_weight' => $s->final_weight !== null ? (float) $s->final_weight : null,
            'final_length' => $s->final_length !== null ? (float) $s->final_length : null,
            'final_width' => $s->final_width !== null ? (float) $s->final_width : null,
            'final_height' => $s->final_height !== null ? (float) $s->final_height : null,
            'final_box_image' => $s->final_box_image,
            'dispatched_at' => $s->dispatched_at?->toIso8601String(),
            'delivered_at' => $s->delivered_at?->toIso8601String(),
            'delivery_rating' => isset($cd['rating']) ? (int) $cd['rating'] : null,
            'delivery_note' => isset($cd['note']) ? (string) $cd['note'] : null,
        ];
    }
}
