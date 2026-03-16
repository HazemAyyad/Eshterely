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
            ->with('shipments.lineItems', 'shipments.trackingEvents', 'payments')
            ->orderByDesc('placed_at')
            ->get();

        return response()->json($orders->map(fn ($o) => $this->formatOrder($o)));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)->with('shipments.lineItems', 'shipments.trackingEvents', 'payments')->findOrFail($id);

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
            'payment_status' => $this->orderPaymentStatus($o),
            'payment_reference' => $this->orderPaymentReference($o),
            'placed_date' => $o->placed_at?->format('M j, Y'),
            'delivered_on' => $o->delivered_at?->format('M j, Y'),
            'total_amount' => '$' . number_format($o->total_amount, 2),
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
                'items' => $s->lineItems->map(fn ($i) => [
                    'id' => (string) $i->id,
                    'name' => $i->name,
                    'store_name' => $i->store_name,
                    'sku' => $i->sku,
                    'price' => '$' . number_format($i->price, 2),
                    'quantity' => $i->quantity,
                    'image_url' => $i->image_url,
                ])->toArray(),
                'tracking_events' => $s->trackingEvents->map(fn ($e) => [
                    'title' => $e->title,
                    'subtitle' => $e->subtitle,
                    'is_highlighted' => $e->is_highlighted,
                ])->toArray(),
                'subtotal' => $s->subtotal ? '$' . number_format($s->subtotal, 2) : null,
                'shipping_fee' => $s->shipping_fee ? '$' . number_format($s->shipping_fee, 2) : null,
                'customs_duties' => $s->customs_duties ? '$' . number_format($s->customs_duties, 2) : null,
            ])->toArray();
        }

        return $base;
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
