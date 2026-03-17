<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('shipments.lineItems', 'shipments.trackingEvents', 'shipments.events', 'payments')
            ->orderByDesc('placed_at')
            ->get();

        return response()->json($orders->map(fn ($o) => $this->formatOrder($o)));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)
            ->with('shipments.lineItems', 'shipments.trackingEvents', 'shipments.events', 'payments')
            ->findOrFail($id);

        return response()->json($this->formatOrder($order, true));
    }

    /**
     * GET /api/orders/{order}/payments
     * List payments for an order. User must own the order.
     */
    public function payments(Request $request, Order $order): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        return PaymentResource::collection($order->payments()->orderByDesc('created_at')->get());
    }

    private function formatOrder(Order $o, bool $detailed = false): array
    {
        $base = [
            'id' => (string) $o->id,
            'order_number' => $o->order_number,
            'origin' => $o->origin,
            'status' => $o->status,
            'status_key' => $this->orderStatusKey($o),
            'payment_status' => $this->orderPaymentStatus($o),
            'payment_reference' => $this->orderPaymentReference($o),
            'placed_date' => $o->placed_at?->format('M j, Y'),
            'delivered_on' => $o->delivered_at?->format('M j, Y'),
            'total' => (float) $o->total_amount,
            'currency' => $o->currency ?? 'USD',
            'total_amount' => '$' . number_format($o->total_amount, 2),
            'estimated' => (bool) $o->estimated,
            'needs_review' => (bool) $o->needs_review,
            'refund_status' => $o->refund_status,
            'estimated_delivery' => $o->estimated_delivery,
            'shipping_address' => $o->shipping_address_text,
        ];

        if ($detailed) {
            $base['shipments'] = $o->shipments->map(fn ($s) => [
                'id' => (string) $s->id,
                'country_code' => $s->country_code,
                'country_label' => $s->country_label,
                'shipping_method' => $s->shipping_method,
                'eta' => $s->eta,
                'carrier' => $s->carrier,
                'tracking_number' => $s->tracking_number,
                'shipment_status' => $s->shipment_status,
                'estimated_delivery_at' => $s->estimated_delivery_at?->toIso8601String(),
                'shipped_at' => $s->shipped_at?->toIso8601String(),
                'delivered_at' => $s->delivered_at?->toIso8601String(),
                'gross_weight_kg' => $s->gross_weight_kg !== null ? (float) $s->gross_weight_kg : null,
                'dimensions' => $s->dimensions,
                'origin' => $s->shipping_snapshot['origin'] ?? null,
                'destination' => $s->shipping_snapshot['destination'] ?? null,
                'items' => $s->lineItems->map(fn ($i) => [
                    'id' => (string) $i->id,
                    'name' => $i->name,
                    'store_name' => $i->store_name,
                    'sku' => $i->sku,
                    // Keep both numeric and formatted price for compatibility
                    'unit_price' => (float) $i->price,
                    'price' => '$' . number_format((float) $i->price, 2),
                    'quantity' => $i->quantity,
                    'image_url' => $i->image_url ?? ($i->product_snapshot['image_url'] ?? null),
                    'product_snapshot' => $i->product_snapshot,
                    'pricing_snapshot' => $i->pricing_snapshot,
                ])->toArray(),
                'tracking_events' => $s->trackingEvents->map(fn ($e) => [
                    'title' => $e->title,
                    'subtitle' => $e->subtitle,
                    'is_highlighted' => $e->is_highlighted,
                ])->toArray(),
                // Shipment events timeline (preferred for tracking screen)
                'events' => $s->events
                    ->sortBy(fn ($e) => $e->event_time ?? $e->created_at)
                    ->values()
                    ->map(fn ($e) => [
                        'type' => $e->event_type,
                        'label' => $e->event_label,
                        'time' => $e->event_time?->toIso8601String(),
                        'location' => $e->location,
                        'notes' => $e->notes,
                        'payload' => $e->payload,
                    ])->toArray(),
                'has_events' => $s->events->isNotEmpty() || $s->trackingEvents->isNotEmpty(),
                'latest_update' => optional(
                    $s->events->sortByDesc(fn ($e) => $e->event_time ?? $e->created_at)->first()
                    ?? $s->trackingEvents->sortByDesc('created_at')->first()
                )->created_at?->toIso8601String(),
                'subtotal' => $s->subtotal ? '$' . number_format($s->subtotal, 2) : null,
                'shipping_fee' => $s->shipping_fee ? '$' . number_format($s->shipping_fee, 2) : null,
                'customs_duties' => $s->customs_duties ? '$' . number_format($s->customs_duties, 2) : null,
            ])->toArray();
        }

        return $base;
    }

    /**
     * Frontend-friendly status key; aligned with OrderResource::statusKey.
     */
    private function orderStatusKey(Order $o): string
    {
        if ($o->needs_review || $o->status === Order::STATUS_UNDER_REVIEW) {
            return 'pending_review';
        }
        return match ($o->status) {
            Order::STATUS_PENDING_PAYMENT => 'pending_payment',
            Order::STATUS_PAID => 'paid',
            Order::STATUS_APPROVED, Order::STATUS_PROCESSING, Order::STATUS_PURCHASED => 'processing',
            Order::STATUS_SHIPPED_TO_WAREHOUSE, Order::STATUS_INTERNATIONAL_SHIPPING, Order::STATUS_IN_TRANSIT => 'shipped',
            Order::STATUS_DELIVERED => 'delivered',
            Order::STATUS_CANCELLED => 'cancelled',
            default => $o->status,
        };
    }

    private function orderPaymentStatus(Order $o): string
    {
        if ($o->status === Order::STATUS_PAID) {
            return 'paid';
        }
        if ($o->status === Order::STATUS_PENDING_PAYMENT && $o->relationLoaded('payments')) {
            return $o->payments->contains(fn ($p) => $p->status->value === 'paid') ? 'paid' : 'pending_payment';
        }
        return $o->status;
    }

    private function orderPaymentReference(Order $o): ?string
    {
        if (! $o->relationLoaded('payments')) {
            return null;
        }
        $paid = $o->payments->filter(fn ($p) => $p->paid_at !== null)->sortByDesc('paid_at')->first();
        return $paid?->reference;
    }
}
