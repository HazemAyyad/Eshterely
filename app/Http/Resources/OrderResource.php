<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;

        $paymentStatus = $this->paymentStatus($order);
        $paymentReference = $this->paymentReference($order);

        return [
            'id' => (string) $order->id,
            'status' => $order->status,
            'status_key' => $this->statusKey($order),
            'total' => (float) $order->total_amount,
            'currency' => $order->currency,
            'estimated' => (bool) $order->estimated,
            'needs_review' => (bool) $order->needs_review,
            'order_number' => $order->order_number,
            'payment_status' => $paymentStatus,
            'payment_reference' => $paymentReference,
            'items' => $this->whenLoaded('shipments', function () use ($order) {
                $lineItems = $order->shipments->flatMap(fn ($s) => $s->lineItems);
                return OrderItemResource::collection($lineItems);
            }),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

    private function statusKey(Order $order): string
    {
        // Frontend-friendly, stable status key. Does not change internal workflow.
        if ($order->needs_review || $order->status === Order::STATUS_UNDER_REVIEW) {
            return 'pending_review';
        }

        return match ($order->status) {
            Order::STATUS_PENDING_PAYMENT => 'pending_payment',
            Order::STATUS_PAID => 'paid',
            Order::STATUS_APPROVED,
            Order::STATUS_PROCESSING,
            Order::STATUS_PURCHASED => 'processing',
            Order::STATUS_SHIPPED_TO_WAREHOUSE,
            Order::STATUS_INTERNATIONAL_SHIPPING,
            Order::STATUS_IN_TRANSIT => 'shipped',
            Order::STATUS_DELIVERED => 'delivered',
            Order::STATUS_CANCELLED => 'cancelled',
            default => $order->status,
        };
    }

    private function paymentStatus(Order $order): string
    {
        if ($order->status === Order::STATUS_PAID) {
            return 'paid';
        }
        if ($order->status === Order::STATUS_PENDING_PAYMENT) {
            $hasPaid = $order->relationLoaded('payments')
                ? $order->payments->contains(fn ($p) => $p->status->value === 'paid')
                : $order->payments()->where('status', 'paid')->exists();
            if ($hasPaid) {
                return 'paid';
            }
            return 'pending_payment';
        }
        return $order->status;
    }

    private function paymentReference(Order $order): ?string
    {
        $payment = $order->relationLoaded('payments')
            ? $order->payments->sortByDesc('paid_at')->first(fn ($p) => $p->paid_at !== null)
            : $order->payments()->whereNotNull('paid_at')->orderByDesc('paid_at')->first();
        return $payment?->reference;
    }
}
