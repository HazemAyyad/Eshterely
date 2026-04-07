<?php

namespace App\Http\Controllers\Api;

use App\Enums\Payment\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\OrderLineItem;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Wallet;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentReferenceGenerator;
use App\Services\Shipments\ShipmentShippingQuoteBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ShipmentsController extends Controller
{
    public function __construct(
        private ShipmentShippingQuoteBuilder $quoteBuilder,
        private PaymentGatewayManager $gatewayManager,
        private PaymentReferenceGenerator $referenceGenerator
    ) {}

    /**
     * GET /api/shipments
     */
    public function index(Request $request): JsonResponse
    {
        $rows = Shipment::query()
            ->where('user_id', $request->user()->id)
            ->with(['items.orderLineItem', 'destinationAddress.country'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'shipments' => $rows->map(fn (Shipment $s) => $this->serializeShipment($s)),
        ]);
    }

    /**
     * POST /api/shipments/create
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'selected_order_item_ids' => 'required|array|min:1',
            'selected_order_item_ids.*' => 'integer|exists:order_line_items,id',
            'destination_address_id' => 'required|integer|exists:addresses,id',
        ]);

        $userId = $request->user()->id;
        $address = Address::where('id', $validated['destination_address_id'])
            ->where('user_id', $userId)
            ->with('country')
            ->firstOrFail();

        $lineItems = OrderLineItem::query()
            ->whereIn('id', $validated['selected_order_item_ids'])
            ->with(['latestWarehouseReceipt', 'shipmentItems'])
            ->get();

        if ($lineItems->count() !== count(array_unique($validated['selected_order_item_ids']))) {
            return response()->json(['message' => 'Invalid line items.', 'status' => 422], 422);
        }

        foreach ($lineItems as $line) {
            if (! $this->userOwnsLineItem($line, $userId)) {
                return response()->json(['message' => 'Forbidden.', 'status' => 403], 403);
            }
            if ($line->fulfillment_status !== OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE) {
                return response()->json(['message' => 'Item is not at warehouse.', 'status' => 422], 422);
            }
            if ($line->shipmentItems()->exists()) {
                return response()->json(['message' => 'Item already assigned to a shipment.', 'status' => 422], 422);
            }
        }

        $pricing = $this->quoteBuilder->build($lineItems, $address);
        $total = round($pricing['shipping_cost'] + $pricing['additional_fees'], 2);

        $shipment = DB::transaction(function () use ($userId, $address, $lineItems, $pricing, $total) {
            $shipment = Shipment::create([
                'user_id' => $userId,
                'destination_address_id' => $address->id,
                'status' => Shipment::STATUS_AWAITING_PAYMENT,
                'shipping_cost' => $pricing['shipping_cost'],
                'additional_fees_total' => $pricing['additional_fees'],
                'total_shipping_payment' => $total,
                'currency' => 'USD',
                'pricing_breakdown' => array_merge(
                    $pricing['quote']->toArray(),
                    [
                        'total_weight_kg' => $pricing['total_weight_kg'],
                        'additional_fees' => $pricing['additional_fees'],
                    ]
                ),
            ]);

            foreach ($lineItems as $line) {
                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'order_line_item_id' => $line->id,
                ]);
                $line->update(['fulfillment_status' => OrderLineItem::FULFILLMENT_READY_FOR_SHIPMENT]);
            }

            return $shipment->fresh(['items.orderLineItem']);
        });

        return response()->json([
            'shipment_id' => (string) $shipment->id,
            'breakdown' => [
                'shipping_cost' => $pricing['shipping_cost'],
                'additional_fees' => $pricing['additional_fees'],
                'total_shipping_payment' => $total,
                'currency' => 'USD',
            ],
            'shipment' => $this->serializeShipment($shipment),
        ], 201);
    }

    /**
     * POST /api/shipments/{shipment}/pay
     */
    public function pay(Request $request, Shipment $shipment): JsonResponse
    {
        if ($shipment->user_id !== $request->user()->id) {
            abort(404);
        }

        if ($shipment->status !== Shipment::STATUS_AWAITING_PAYMENT) {
            return response()->json(['message' => 'Shipment is not awaiting payment.', 'status' => 422], 422);
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:wallet,gateway',
            'gateway' => 'nullable|string|in:square,stripe',
        ]);

        $amount = round((float) $shipment->total_shipping_payment, 2);
        if ($amount <= 0) {
            return response()->json(['message' => 'Nothing to pay.', 'status' => 422], 422);
        }

        if ($validated['payment_method'] === 'wallet') {
            return $this->payWithWallet($request, $shipment, $amount);
        }

        return $this->payWithGateway($request, $shipment, $amount, $validated['gateway'] ?? null);
    }

    private function payWithWallet(Request $request, Shipment $shipment, float $amount): JsonResponse
    {
        try {
            DB::transaction(function () use ($request, $shipment, $amount) {
                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $request->user()->id],
                    ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
                );
                $wallet = Wallet::whereKey($wallet->id)->lockForUpdate()->first();
                if ((float) $wallet->available_balance + 0.00001 < $amount) {
                    throw new \RuntimeException('INSUFFICIENT_WALLET');
                }

                $wallet->available_balance = max(0, (float) $wallet->available_balance - $amount);
                $wallet->save();

                $reference = $this->referenceGenerator->generate();

                Payment::create([
                    'user_id' => $request->user()->id,
                    'order_id' => null,
                    'shipment_id' => $shipment->id,
                    'provider' => 'wallet',
                    'currency' => $shipment->currency ?: 'USD',
                    'amount' => $amount,
                    'status' => PaymentStatus::Paid,
                    'reference' => $reference,
                    'idempotency_key' => 'shipment_wallet_'.$shipment->id,
                    'paid_at' => now(),
                    'metadata' => [
                        'payment_method' => 'wallet',
                        'source' => 'shipment_shipping',
                    ],
                ]);

                $wallet->transactions()->create([
                    'type' => 'payment',
                    'title' => 'Shipment #'.$shipment->id.' shipping',
                    'amount' => -$amount,
                    'subtitle' => 'SHIPMENT',
                    'reference_type' => 'shipment',
                    'reference_id' => $shipment->id,
                ]);

                $shipment->update(['status' => Shipment::STATUS_PAID]);
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_WALLET') {
                return response()->json([
                    'message' => 'Insufficient wallet balance.',
                    'error_code' => 'insufficient_wallet_balance',
                    'status' => 422,
                ], 422);
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'shipment' => $this->serializeShipment($shipment->fresh(['items.orderLineItem'])),
        ]);
    }

    private function payWithGateway(Request $request, Shipment $shipment, float $amount, ?string $gatewayCode): JsonResponse
    {
        try {
            $gateway = $gatewayCode !== null && trim($gatewayCode) !== ''
                ? $this->gatewayManager->resolve(trim($gatewayCode))
                : $this->gatewayManager->resolveDefault();
        } catch (InvalidArgumentException) {
            return response()->json([
                'message' => 'Selected payment gateway is not available.',
                'error_key' => 'gateway_unavailable',
                'status' => 422,
            ], 422);
        }

        $reference = $this->referenceGenerator->generate();

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'order_id' => null,
            'shipment_id' => $shipment->id,
            'provider' => $gateway->gatewayCode(),
            'currency' => $shipment->currency ?: 'USD',
            'amount' => $amount,
            'status' => PaymentStatus::Pending,
            'reference' => $reference,
            'idempotency_key' => 'shipment_gateway_'.$shipment->id.'_'.$reference,
            'metadata' => [
                'payment_method' => 'gateway',
                'source' => 'shipment_shipping',
            ],
        ]);

        try {
            $session = $gateway->createShipmentShippingCheckoutSession($payment);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not start payment: '.$e->getMessage(),
                'status' => 422,
            ], 422);
        }

        $checkoutUrl = is_string($session['checkout_url'] ?? null) ? trim((string) $session['checkout_url']) : '';
        if ($checkoutUrl === '') {
            return response()->json(['message' => 'Gateway did not return a checkout URL.', 'status' => 422], 422);
        }

        return response()->json([
            'success' => true,
            'checkout_url' => $checkoutUrl,
            'gateway' => $gateway->gatewayCode(),
            'payment_reference' => $reference,
        ]);
    }

    private function userOwnsLineItem(OrderLineItem $line, int $userId): bool
    {
        return OrderLineItem::query()
            ->where('order_line_items.id', $line->id)
            ->join('order_shipments', 'order_line_items.order_shipment_id', '=', 'order_shipments.id')
            ->join('orders', 'order_shipments.order_id', '=', 'orders.id')
            ->where('orders.user_id', $userId)
            ->exists();
    }

    private function serializeShipment(Shipment $s): array
    {
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
            'shipping_cost' => round((float) $s->shipping_cost, 2),
            'additional_fees_total' => round((float) $s->additional_fees_total, 2),
            'total_shipping_payment' => round((float) $s->total_shipping_payment, 2),
            'currency' => $s->currency,
            'items' => $s->items->map(function (ShipmentItem $si) {
                $line = $si->orderLineItem;

                return [
                    'order_line_item_id' => (string) $line->id,
                    'name' => $line->name,
                    'image_url' => $line->image_url,
                    'quantity' => $line->quantity,
                ];
            })->values()->all(),
        ];
    }
}
