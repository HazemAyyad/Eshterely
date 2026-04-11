<?php

namespace App\Http\Controllers\Api;

use App\Enums\Payment\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\OrderLineItem;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentReferenceGenerator;
use App\Services\Shipments\ShipmentDraftFinalizationService;
use App\Services\Shipments\ShipmentShippingQuoteBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ShipmentsController extends Controller
{
    public function __construct(
        private ShipmentShippingQuoteBuilder $quoteBuilder,
        private PaymentGatewayManager $gatewayManager,
        private PaymentReferenceGenerator $referenceGenerator,
        private ShipmentDraftFinalizationService $draftFinalization
    ) {}

    /**
     * GET /api/shipments
     */
    public function index(Request $request): JsonResponse
    {
        $rows = Shipment::query()
            ->where('user_id', $request->user()->id)
            ->with(['items.orderLineItem.latestWarehouseReceipt', 'destinationAddress.country', 'destinationAddress.city', 'payments'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'shipments' => $rows->map(fn (Shipment $s) => $this->serializeShipment($s)),
        ]);
    }

    /**
     * POST /api/shipments/create
     *
     * Creates a draft shipment only (no line locks). Payment step attaches lines after successful payment.
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

        $selectedIds = array_map('intval', array_unique($validated['selected_order_item_ids']));

        $lineItems = OrderLineItem::query()
            ->whereIn('id', $selectedIds)
            ->with(['latestWarehouseReceipt', 'shipmentItems'])
            ->get();

        if ($lineItems->count() !== count($selectedIds)) {
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

        try {
            $this->assertNoOverlappingDraft($userId, $selectedIds);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'status' => 422], 422);
        }

        $pricing = $this->quoteBuilder->build($lineItems, $address);
        $total = round($pricing['shipping_cost'] + $pricing['additional_fees'], 2);

        $shipment = DB::transaction(function () use ($userId, $address, $pricing, $total, $selectedIds) {
            return Shipment::create([
                'user_id' => $userId,
                'destination_address_id' => $address->id,
                'status' => Shipment::STATUS_DRAFT,
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
                'draft_payload' => [
                    'selected_order_item_ids' => $selectedIds,
                ],
            ]);
        });

        $shipment->load(['items.orderLineItem.latestWarehouseReceipt', 'destinationAddress.country', 'destinationAddress.city']);

        return response()->json([
            'shipment_id' => (string) $shipment->id,
            'breakdown' => [
                'shipping_cost' => $pricing['shipping_cost'],
                'additional_fees' => $pricing['additional_fees'],
                'total_shipping_payment' => $total,
                'currency' => 'USD',
            ],
            'shipment' => $this->serializeShipment($shipment),
            'checkout_payment_mode' => $this->getCheckoutPaymentMode(),
        ], 201);
    }

    /**
     * DELETE /api/shipments/{shipment} — abandon a draft (no items were locked).
     */
    public function destroy(Request $request, Shipment $shipment): JsonResponse
    {
        if ($shipment->user_id !== $request->user()->id) {
            abort(404);
        }

        if ($shipment->status !== Shipment::STATUS_DRAFT) {
            return response()->json(['message' => 'Only draft shipments can be cancelled.', 'status' => 422], 422);
        }

        $shipment->delete();

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/shipments/{shipment}/pay
     */
    public function pay(Request $request, Shipment $shipment): JsonResponse
    {
        if ($shipment->user_id !== $request->user()->id) {
            abort(404);
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:wallet,gateway',
            'gateway' => 'nullable|string|in:square,stripe',
        ]);

        $modeErr = $this->validatePaymentMethodAgainstCheckoutMode($validated['payment_method']);
        if ($modeErr !== null) {
            return response()->json(['message' => $modeErr, 'status' => 422], 422);
        }

        $amount = round((float) $shipment->total_shipping_payment, 2);
        if ($amount <= 0) {
            return response()->json(['message' => 'Nothing to pay.', 'status' => 422], 422);
        }

        if ($shipment->status === Shipment::STATUS_DRAFT && $validated['payment_method'] === 'gateway') {
            return $this->payWithGateway($request, $shipment, $amount, $validated['gateway'] ?? null);
        }

        if ($shipment->status !== Shipment::STATUS_AWAITING_PAYMENT && $shipment->status !== Shipment::STATUS_DRAFT) {
            return response()->json(['message' => 'Shipment is not awaiting payment.', 'status' => 422], 422);
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
                if ($shipment->status === Shipment::STATUS_DRAFT) {
                    $payload = $shipment->draft_payload;
                    $ids = is_array($payload) ? ($payload['selected_order_item_ids'] ?? []) : [];
                    if (! is_array($ids) || $ids === []) {
                        throw new \RuntimeException('MISSING_DRAFT_SELECTION');
                    }
                    $this->draftFinalization->attachLinesAndSetAwaitingPayment(
                        $shipment,
                        array_map('intval', $ids),
                        (int) $request->user()->id
                    );
                    $shipment->refresh();
                }

                if ($shipment->status !== Shipment::STATUS_AWAITING_PAYMENT) {
                    throw new \RuntimeException('INVALID_SHIPMENT_STATE');
                }

                $wallet = \App\Models\Wallet::firstOrCreate(
                    ['user_id' => $request->user()->id],
                    ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
                );
                $wallet = \App\Models\Wallet::whereKey($wallet->id)->lockForUpdate()->first();
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
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'status' => 422], 422);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'MISSING_DRAFT_SELECTION') {
                return response()->json(['message' => 'Draft shipment is missing selection.', 'status' => 422], 422);
            }
            if ($e->getMessage() === 'INVALID_SHIPMENT_STATE') {
                return response()->json(['message' => 'Shipment is not awaiting payment.', 'status' => 422], 422);
            }
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
            'shipment' => $this->serializeShipment($shipment->fresh(['items.orderLineItem', 'payments'])),
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

    /**
     * @param  list<int>  $selectedIds
     */
    private function assertNoOverlappingDraft(int $userId, array $selectedIds): void
    {
        $drafts = Shipment::query()
            ->where('user_id', $userId)
            ->where('status', Shipment::STATUS_DRAFT)
            ->get(['id', 'draft_payload']);

        foreach ($drafts as $d) {
            $existing = $d->draft_payload['selected_order_item_ids'] ?? [];
            if (! is_array($existing)) {
                continue;
            }
            $overlap = array_intersect($selectedIds, array_map('intval', $existing));
            if ($overlap !== []) {
                throw new InvalidArgumentException('Another draft shipment already includes one of these items. Complete or cancel it first.');
            }
        }
    }

    private function getCheckoutPaymentMode(): string
    {
        if (! Schema::hasTable('payment_gateway_settings')) {
            return 'gateway_only';
        }
        $row = DB::table('payment_gateway_settings')->first();
        if (! $row || ! isset($row->checkout_payment_mode)) {
            return 'gateway_only';
        }
        $m = strtolower(trim((string) $row->checkout_payment_mode));
        $allowed = ['wallet_only', 'gateway_only', 'wallet_and_gateway'];

        return in_array($m, $allowed, true) ? $m : 'gateway_only';
    }

    private function validatePaymentMethodAgainstCheckoutMode(string $paymentMethod): ?string
    {
        $mode = $this->getCheckoutPaymentMode();
        if ($mode === 'wallet_only' && $paymentMethod !== 'wallet') {
            return 'Checkout is configured for wallet payment only.';
        }
        if ($mode === 'gateway_only' && $paymentMethod !== 'gateway') {
            return 'Checkout is configured for card payment only.';
        }

        return null;
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
        $latestPayment = $s->relationLoaded('payments')
            ? $s->payments->sortByDesc('created_at')->first()
            : $s->payments()->orderByDesc('id')->first();

        $paymentStatus = $latestPayment?->status?->value;

        $addr = $s->relationLoaded('destinationAddress') ? $s->destinationAddress : null;
        $destinationSummary = null;
        if ($addr) {
            $cityName = $addr->relationLoaded('city') ? $addr->city?->name : null;
            $parts = array_filter([
                $addr->address_line ?? null,
                $cityName,
                $addr->country?->name ?? null,
            ], fn ($v) => is_string($v) && trim($v) !== '');
            $destinationSummary = $parts !== [] ? implode(', ', $parts) : null;
        }

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
            'payment_status' => $paymentStatus,
            'destination_summary' => $destinationSummary,
            'items' => $s->items->map(function (ShipmentItem $si) {
                $line = $si->orderLineItem;
                $r = $line->relationLoaded('latestWarehouseReceipt') ? $line->latestWarehouseReceipt : null;

                return [
                    'order_line_item_id' => (string) $line->id,
                    'name' => $line->name,
                    'image_url' => $line->image_url,
                    'quantity' => $line->quantity,
                    'weight_kg' => $line->weight_kg !== null ? (float) $line->weight_kg : null,
                    'dimensions' => $line->dimensions,
                    'receipt' => $r ? [
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
            })->values()->all(),
        ];
    }
}
