<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Support\OrderExecutionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;

        $order->loadMissing(['payments', 'lineItems.shipmentItems.shipment']);

        $hasPaid = $order->payments->contains(fn ($p) => $p->status->value === 'paid');
        $executionStatus = OrderExecutionStatus::resolve($order, $order->lineItems, $hasPaid);

        $paymentStatus = $this->paymentStatus($order);
        $paymentReference = $this->paymentReference($order);

        return [
            'id' => (string) $order->id,
            'purchase_assistant_request_id' => $order->purchase_assistant_request_id !== null
                ? (string) $order->purchase_assistant_request_id
                : null,
            'is_purchase_assistant' => $order->purchase_assistant_request_id !== null,
            'status' => $order->status,
            'execution_status' => $executionStatus,
            'status_key' => $executionStatus,
            'total' => (float) $order->total_amount,
            'currency' => $order->currency,
            'estimated' => (bool) $order->estimated,
            'needs_review' => (bool) $order->needs_review,
            'order_number' => $order->order_number,
            'payment_status' => $paymentStatus,
            'payment_reference' => $paymentReference,
            'promo_code' => $order->promo_code,
            'promo_discount_amount' => $order->promo_discount_amount !== null ? (float) $order->promo_discount_amount : null,
            'wallet_applied_amount' => $order->wallet_applied_amount !== null ? (float) $order->wallet_applied_amount : null,
            'amount_due_now' => $order->amount_due_now !== null ? (float) $order->amount_due_now : null,
            'items' => $this->whenLoaded('shipments', function () use ($order) {
                $lineItems = $order->shipments->flatMap(fn ($s) => $s->lineItems);
                return OrderItemResource::collection($lineItems);
            }),
            'price_lines' => $this->when($order->exists, function () use ($order) {
                return DB::table('order_price_lines')
                    ->where('order_id', $order->id)
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($line) => [
                        'label' => $line->label,
                        'amount' => $line->amount,
                        'is_discount' => (bool) $line->is_discount,
                    ])
                    ->toArray();
            }),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
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
