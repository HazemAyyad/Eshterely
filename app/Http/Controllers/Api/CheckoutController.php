<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\Payment\PaymentStatus;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\OrderShipment;
use App\Models\Payment;
use App\Models\Wallet;
use App\Services\Payments\PaymentReferenceGenerator;
use App\Services\PromoCodeService;
use App\Services\Shipping\ShippingPricingConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(
        private PromoCodeService $promoService,
        private ShippingPricingConfigService $shippingConfig
    ) {}

    public function validatePromo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $summary = $this->buildCheckoutSummary($request, null, false);
        $evaluation = $this->promoService->evaluate($validated['code'], $request->user(), $summary['base_total']);

        $payload = $this->promoResponsePayload($evaluation, $summary['base_total']);
        if (! $evaluation['valid']) {
            $status = $evaluation['error_key'] === 'not_found' ? 404 : 422;
            return response()->json($payload, $status);
        }

        return response()->json($payload);
    }

    /**
     * Checkout review.
     *
     * Contract goals for Flutter:
     * - Do not invent fake shipping/fees.
     * - Provide an explicit numeric breakdown + flags for estimated / needs_review.
     * - Keep legacy string fields for backward compatibility where they already exist.
     */
    public function review(Request $request): JsonResponse
    {
        $promoCode = $this->normalizePromoCode($request->input('promo_code', $request->query('promo_code')));
        $summary = $this->buildCheckoutSummary($request, $promoCode, true);

        return response()->json($this->reviewResponsePayload($summary));
    }

    public function confirm(Request $request): JsonResponse
    {
        $promoCode = $this->normalizePromoCode($request->input('promo_code'));
        $useWallet = $request->boolean('use_wallet_balance', true);
        $summary = $this->buildCheckoutSummary($request, $promoCode, $useWallet);

        if ($summary['checkout_items']->isEmpty()) {
            return response()->json(['message' => 'Cart is empty', 'errors' => [], 'status' => 400], 400);
        }

        if (! $summary['default_address']) {
            return response()->json(['message' => 'Please set a default address', 'errors' => [], 'status' => 400], 400);
        }

        if ($promoCode !== '' && ! $summary['promo_evaluation']['valid']) {
            return response()->json([
                'message' => $summary['promo_evaluation']['message'],
                'error_key' => $summary['promo_evaluation']['error_key'],
                'errors' => [],
                'status' => 422,
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($request, $summary, $promoCode, $useWallet) {
                $lockedEvaluation = null;
                if ($promoCode !== '') {
                    $lockedEvaluation = $this->promoService->evaluate($promoCode, $request->user(), $summary['base_total'], true);
                    if (! $lockedEvaluation['valid']) {
                        throw new \RuntimeException($lockedEvaluation['message']);
                    }
                }

                $promoDiscount = $lockedEvaluation['discount_amount'] ?? $summary['promo_discount_amount'];
                $totalAfterPromo = round(max(0, $summary['base_total'] - $promoDiscount), 2);
                $walletApplied = $useWallet ? round(min($summary['wallet_balance'], $totalAfterPromo), 2) : 0.0;
                $amountDueNow = round(max(0, $totalAfterPromo - $walletApplied), 2);
                $needsReview = $summary['needs_review'];
                $estimated = $summary['estimated'];
                $status = ($needsReview || $estimated) ? Order::STATUS_UNDER_REVIEW : ($amountDueNow > 0 ? Order::STATUS_PENDING_PAYMENT : Order::STATUS_PAID);

                $orderNumber = $this->buildOrderNumber();
                $originCountries = $summary['checkout_items']->pluck('country')->filter()->unique()->values();
                $origin = $originCountries->count() > 1
                    ? 'multi_origin'
                    : (strtoupper((string) ($originCountries->first() ?? 'US')) === 'TR' ? 'turkey' : 'usa');

                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'order_number' => $orderNumber,
                    'origin' => $origin,
                    'status' => $status,
                    'placed_at' => $status === Order::STATUS_PAID ? now() : null,
                    'total_amount' => $totalAfterPromo,
                    'order_total_snapshot' => $totalAfterPromo,
                    'shipping_total_snapshot' => round($summary['shipping'], 2),
                    'currency' => $summary['currency'],
                    'shipping_address_id' => $summary['default_address']->id,
                    'shipping_address_text' => $summary['default_address']->address_line ?? $summary['default_address']->street_address,
                    'promo_code_id' => ($lockedEvaluation['promo'] ?? null)?->id,
                    'promo_code' => ($lockedEvaluation['promo'] ?? null)?->code,
                    'promo_discount_amount' => $promoDiscount,
                    'wallet_applied_amount' => $walletApplied,
                    'amount_due_now' => $amountDueNow,
                    'estimated' => $estimated,
                    'needs_review' => $needsReview || $estimated,
                    'review_state' => array_filter([
                        Order::REVIEW_STATE_NEEDS_ADMIN_REVIEW => $needsReview ? true : null,
                        Order::REVIEW_STATE_NEEDS_SHIPPING_COMPLETION => $summary['shipping_has_unknown'] ? true : null,
                    ], fn ($v) => $v !== null),
                ]);

                $this->createOrderShipmentsAndItems($order, $summary['checkout_items']);
                $this->persistPriceLines($order, $summary['currency'], $summary['subtotal'], $summary['shipping'], $promoDiscount, $walletApplied, $totalAfterPromo, $amountDueNow);

                if ($walletApplied > 0) {
                    $wallet = Wallet::whereKey($summary['wallet']->id)->lockForUpdate()->first();
                    if ($wallet === null) {
                        throw new \RuntimeException('Wallet not found.');
                    }
                    if ((float) $wallet->available_balance + 0.00001 < $walletApplied) {
                        throw new \RuntimeException('Insufficient wallet balance.');
                    }
                    $wallet->available_balance = max(0, (float) $wallet->available_balance - $walletApplied);
                    $wallet->save();
                    $wallet->transactions()->create([
                        'type' => 'payment',
                        'title' => 'Order #' . $orderNumber,
                        'amount' => -$walletApplied,
                        'subtitle' => ($needsReview || $estimated) ? 'WALLET · pending review' : 'WALLET',
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                    ]);

                    Payment::create([
                        'user_id' => $request->user()->id,
                        'order_id' => $order->id,
                        'provider' => 'wallet',
                        'currency' => $summary['currency'],
                        'amount' => $walletApplied,
                        'status' => PaymentStatus::Paid,
                        'reference' => app(PaymentReferenceGenerator::class)->generate(),
                        'idempotency_key' => 'wallet_checkout_' . $order->id,
                        'paid_at' => now(),
                        'metadata' => [
                            'payment_method' => 'wallet',
                            'source' => 'checkout',
                            'pending_review' => $needsReview || $estimated,
                        ],
                    ]);
                }

                if ($lockedEvaluation['promo'] ?? null) {
                    $this->promoService->recordRedemption(
                        $lockedEvaluation['promo'],
                        $request->user(),
                        $order,
                        [
                            'subtotal_amount' => $summary['subtotal'],
                            'shipping_amount' => $summary['shipping'],
                            'total_before_amount' => $summary['base_total'],
                            'discount_amount' => $promoDiscount,
                            'wallet_applied_amount' => $walletApplied,
                            'total_after_amount' => $amountDueNow,
                            'status' => 'applied',
                            'metadata' => [
                                'promo_code' => $promoCode,
                                'wallet_enabled' => $useWallet,
                            ],
                        ]
                    );
                }

                return [
                    'order' => $order->fresh(),
                    'order_number' => $orderNumber,
                    'currency' => $summary['currency'],
                    'subtotal' => $summary['subtotal'],
                    'shipping' => $summary['shipping'],
                    'promo_discount' => $promoDiscount,
                    'wallet_balance' => $summary['wallet_balance'],
                    'wallet_applied_amount' => $walletApplied,
                    'amount_due_now' => $amountDueNow,
                    'total' => $totalAfterPromo,
                    'estimated' => $estimated,
                    'needs_review' => $needsReview || $estimated,
                    'shipping_estimated' => $summary['shipping_has_unknown'],
                    'status' => $status,
                ];
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [],
                'status' => 422,
            ], 422);
        }

        return response()->json([
            'message' => 'Order placed',
            'order_id' => (string) $result['order']->id,
            'order_number' => $result['order_number'],
            'currency' => $result['currency'],
            'promo_code' => $result['order']->promo_code,
            'promo_discount_amount' => (float) $result['promo_discount'],
            'wallet_applied_amount' => (float) $result['wallet_applied_amount'],
            'amount_due_now' => (float) $result['amount_due_now'],
            'total' => $this->formatMoney($result['total']),
            'pricing' => [
                'subtotal' => round($result['subtotal'], 2),
                'shipping' => round($result['shipping'], 2),
                'discounts' => round($result['promo_discount'], 2),
                'wallet_balance' => round($result['wallet_balance'], 2),
                'wallet_applied_amount' => round($result['wallet_applied_amount'], 2),
                'amount_due_now' => round($result['amount_due_now'], 2),
                'total' => round($result['total'], 2),
                'estimated' => $result['estimated'],
                'needs_review' => $result['needs_review'],
                'shipping_estimated' => $result['shipping_estimated'],
                'breakdown' => $this->buildBreakdown(
                    $result['subtotal'],
                    $result['shipping'],
                    $result['promo_discount'],
                    $result['wallet_applied_amount'],
                    $result['total'],
                    $result['amount_due_now']
                ),
            ],
            'payment_required' => $result['status'] === Order::STATUS_PENDING_PAYMENT && $result['amount_due_now'] > 0,
            'price_lines' => $this->buildPriceLines(
                $result['currency'],
                $result['subtotal'],
                $result['shipping'],
                $result['promo_discount'],
                $result['wallet_applied_amount']
            ),
        ], 201);
    }

    /**
     * Build checkout totals and selection state from cart items.
     *
     * @return array<string, mixed>
     */
    private function buildCheckoutSummary(Request $request, ?string $promoCode = null, bool $useWallet = true): array
    {
        $items = CartItem::where('user_id', $request->user()->id)
            ->whereNull('draft_order_id')
            ->get();

        // Include all non-rejected items so estimated shipping appears and checkout
        // can proceed immediately after adding an item to cart.
        $checkoutItems = $items->filter(
            fn (CartItem $i) => ($i->review_status ?? CartItem::REVIEW_STATUS_PENDING) !== CartItem::REVIEW_STATUS_REJECTED
        )->values();
        $defaultAddress = Address::where('user_id', $request->user()->id)
            ->where('is_default', true)
            ->with('country')
            ->first();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
        );

        $currency = strtoupper(trim((string) ($checkoutItems->first()?->currency ?? $items->first()?->currency ?? 'USD')));
        if ($currency === '') {
            $currency = 'USD';
        }

        $subtotal = (float) $checkoutItems->sum(fn ($i) => (float) $i->unit_price * (int) $i->quantity);
        $shippingKnown = (float) $checkoutItems->sum(fn ($i) => $i->shipping_cost !== null ? (float) $i->shipping_cost * (int) $i->quantity : 0.0);
        $shippingHasUnknown = $checkoutItems->contains(fn ($i) => $i->shipping_cost === null);
        $needsReview = $checkoutItems->contains(fn ($i) => (bool) $i->needs_review);
        $estimated = $shippingHasUnknown || $checkoutItems->contains(fn ($i) => (bool) $i->estimated);
        $baseTotal = round($subtotal + $shippingKnown, 2);

        $promoEvaluation = [
            'valid' => false,
            'message' => '',
            'error_key' => null,
            'promo' => null,
            'discount_amount' => 0.0,
            'code' => $this->normalizePromoCode($promoCode),
            'base_amount' => $baseTotal,
        ];
        $promoDiscountAmount = 0.0;
        if ($promoEvaluation['code'] !== '') {
            $promoEvaluation = $this->promoService->evaluate($promoEvaluation['code'], $request->user(), $baseTotal);
            $promoDiscountAmount = $promoEvaluation['valid'] ? (float) $promoEvaluation['discount_amount'] : 0.0;
        }

        $totalAfterPromo = round(max(0, $baseTotal - $promoDiscountAmount), 2);
        $walletBalance = (float) $wallet->available_balance;
        $walletApplied = $useWallet ? round(min($walletBalance, $totalAfterPromo), 2) : 0.0;
        $amountDueNow = round(max(0, $totalAfterPromo - $walletApplied), 2);
        $shipments = $this->buildShipmentsPayload($checkoutItems);

        return [
            'items' => $items,
            'checkout_items' => $checkoutItems,
            'default_address' => $defaultAddress,
            'wallet' => $wallet,
            'currency' => $currency,
            'subtotal' => round($subtotal, 2),
            'shipping' => round($shippingKnown, 2),
            'shipping_has_unknown' => $shippingHasUnknown,
            'needs_review' => $needsReview,
            'estimated' => $estimated,
            'base_total' => $baseTotal,
            'promo_evaluation' => $promoEvaluation,
            'promo_discount_amount' => round($promoDiscountAmount, 2),
            'total_after_promo' => $totalAfterPromo,
            'wallet_balance' => round($walletBalance, 2),
            'wallet_applied_amount' => $walletApplied,
            'amount_due_now' => $amountDueNow,
            'shipments' => $shipments,
            'price_lines' => $this->buildPriceLines($currency, $subtotal, $shippingKnown, $promoDiscountAmount, $walletApplied),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CartItem>  $checkoutItems
     * @return array<int, array<string, mixed>>
     */
    private function buildShipmentsPayload($checkoutItems): array
    {
        return $checkoutItems
            ->groupBy(fn ($i) => $i->country ?? 'Other')
            ->map(function ($group, $country) {
                $groupNeedsReview = $group->contains(fn ($i) => (bool) $i->needs_review);
                $groupEstimated = $group->contains(fn ($i) => (bool) $i->estimated || $i->shipping_cost === null);

                return [
                    'origin_label' => $country . ' Shipment',
                    'needs_review' => $groupNeedsReview,
                    'estimated' => $groupEstimated,
                    'items' => $group->map(function ($i) {
                        $itemSubtotal = (float) $i->unit_price * (int) $i->quantity;
                        $unitShipping = $i->shipping_cost !== null ? (float) $i->shipping_cost : null;
                        $shippingAmount = $unitShipping !== null ? $unitShipping * (int) $i->quantity : null;

                        return [
                            'id' => (string) $i->id,
                            'name' => $i->name,
                            'price' => $this->formatMoney((float) $i->unit_price),
                            'shipping_cost' => $shippingAmount !== null ? $this->formatMoney($shippingAmount) : null,
                            'unit_price' => (float) $i->unit_price,
                            'quantity' => (int) $i->quantity,
                            'subtotal' => round($itemSubtotal, 2),
                            'unit_shipping_amount' => $unitShipping,
                            'shipping_amount' => $shippingAmount !== null ? round($shippingAmount, 2) : null,
                            'currency' => $i->currency ?? 'USD',
                            'image_url' => $i->image_url,
                            'review_status' => $i->review_status ?? CartItem::REVIEW_STATUS_PENDING,
                            'needs_review' => (bool) $i->needs_review,
                            'estimated' => (bool) $i->estimated || $i->shipping_cost === null,
                            'missing_fields' => $i->missing_fields ?? [],
                            'carrier' => $i->carrier,
                            'pricing_mode' => $i->pricing_mode,
                        ];
                    })->values()->toArray(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPriceLines(string $currency, float $subtotal, float $shipping, float $promoDiscount, float $walletApplied): array
    {
        $lines = [
            [
                'label' => 'Subtotal',
                'amount' => $this->formatMoney($subtotal, $currency),
                'is_discount' => false,
            ],
            [
                'label' => 'Shipping',
                'amount' => $this->formatMoney($shipping, $currency),
                'is_discount' => false,
            ],
        ];

        if ($promoDiscount > 0) {
            $lines[] = [
                'label' => 'Promo discount',
                'amount' => $this->formatSignedMoney(-$promoDiscount, $currency),
                'is_discount' => true,
            ];
        }

        if ($walletApplied > 0) {
            $lines[] = [
                'label' => 'Wallet applied',
                'amount' => $this->formatSignedMoney(-$walletApplied, $currency),
                'is_discount' => true,
            ];
        }

        return $lines;
    }

    private function persistPriceLines(Order $order, string $currency, float $subtotal, float $shipping, float $promoDiscount, float $walletApplied, float $totalAfterPromo, float $amountDueNow): void
    {
        $rows = $this->buildPriceLines($currency, $subtotal, $shipping, $promoDiscount, $walletApplied);
        foreach ($rows as $row) {
            DB::table('order_price_lines')->insert([
                'order_id' => $order->id,
                'label' => $row['label'],
                'amount' => $row['amount'],
                'is_discount' => $row['is_discount'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createOrderShipmentsAndItems(Order $order, $checkoutItems): void
    {
        $byCountry = $checkoutItems->groupBy(fn ($i) => $i->country ?? 'Other');
        foreach ($byCountry as $country => $group) {
            $shipment = OrderShipment::create([
                'order_id' => $order->id,
                'country_code' => strlen((string) $country) === 2 ? strtoupper((string) $country) : 'US',
                'country_label' => $country . ' Shipment',
                'shipping_method' => $group->first()?->shipping_snapshot['method'] ?? null,
                'eta' => $group->first()?->shipping_snapshot['eta'] ?? null,
                'carrier' => $group->first()?->carrier,
                'subtotal' => $group->sum(fn ($i) => (float) $i->unit_price * (int) $i->quantity),
                'shipping_fee' => $group->sum(fn ($i) => $i->shipping_cost !== null ? (float) $i->shipping_cost * (int) $i->quantity : 0.0),
                'shipping_snapshot' => $group->first()?->shipping_snapshot,
            ]);

            foreach ($group as $i) {
                OrderLineItem::create([
                    'order_shipment_id' => $shipment->id,
                    'source_type' => $i->source,
                    'cart_item_id' => $i->id,
                    'imported_product_id' => $i->imported_product_id,
                    'name' => $i->name,
                    'store_name' => $i->store_name,
                    'sku' => $i->product_id ? (string) $i->product_id : null,
                    'price' => (float) $i->unit_price,
                    'quantity' => (int) $i->quantity,
                    'image_url' => $i->image_url,
                    'product_snapshot' => [
                        'title' => $i->name,
                        'image_url' => $i->image_url,
                        'store_key' => $i->store_key,
                        'store_name' => $i->store_name,
                        'product_url' => $i->product_url,
                        'country' => $i->country,
                    ],
                    'pricing_snapshot' => $i->pricing_snapshot,
                    'review_metadata' => [
                        'review_status' => $i->review_status,
                        'needs_review' => (bool) $i->needs_review,
                    ],
                    'estimated' => (bool) $i->estimated,
                    'missing_fields' => $i->missing_fields,
                ]);
            }
        }
    }

    private function reviewResponsePayload(array $summary): array
    {
        $promoEvaluation = $summary['promo_evaluation'];
        $total = $summary['total_after_promo'];

        return [
            'shipping_address_short' => $summary['default_address'] ? $this->shortAddress($summary['default_address']) : '',
            'consolidation_savings' => '$0.00',
            'wallet_balance_enabled' => true,
            'wallet_balance' => $this->formatMoney($summary['wallet_balance'], $summary['currency']),
            'subtotal' => $this->formatMoney($summary['subtotal'], $summary['currency']),
            'shipping' => $this->formatMoney($summary['shipping'], $summary['currency']),
            'insurance' => '$0.00',
            'promo_code' => $promoEvaluation['code'],
            'promo_valid' => (bool) $promoEvaluation['valid'],
            'promo_message' => $promoEvaluation['message'],
            'promo_discount_amount' => round((float) $summary['promo_discount_amount'], 2),
            'wallet_applied_amount' => round((float) $summary['wallet_applied_amount'], 2),
            'amount_due_now' => round((float) $summary['amount_due_now'], 2),
            'total' => $this->formatMoney($total, $summary['currency']),
            'shipments' => $summary['shipments'],
            'price_lines' => $summary['price_lines'],
            'currency' => $summary['currency'],
            'pricing' => [
                'subtotal' => round($summary['subtotal'], 2),
                'shipping' => round($summary['shipping'], 2),
                'discounts' => round($summary['promo_discount_amount'], 2),
                'wallet_balance' => round($summary['wallet_balance'], 2),
                'wallet_applied_amount' => round($summary['wallet_applied_amount'], 2),
                'amount_due_now' => round($summary['amount_due_now'], 2),
                'total' => round($total, 2),
                'estimated' => $summary['estimated'],
                'needs_review' => $summary['needs_review'],
                'shipping_estimated' => $summary['shipping_has_unknown'],
                'breakdown' => $this->buildBreakdown(
                    $summary['subtotal'],
                    $summary['shipping'],
                    $summary['promo_discount_amount'],
                    $summary['wallet_applied_amount'],
                    $total,
                    $summary['amount_due_now']
                ),
            ],
        ];
    }

    private function promoResponsePayload(array $evaluation, float $baseAmount): array
    {
        return [
            'valid' => (bool) $evaluation['valid'],
            'message' => $evaluation['message'],
            'error_key' => $evaluation['error_key'],
            'code' => $evaluation['code'],
            'base_amount' => round($baseAmount, 2),
            'discount_amount' => round((float) $evaluation['discount_amount'], 2),
            'discount' => [
                'type' => $evaluation['promo']?->discount_type,
                'value' => $evaluation['promo'] ? (float) $evaluation['promo']->discount_value : null,
            ],
        ];
    }

    private function buildBreakdown(float $subtotal, float $shipping, float $promoDiscount, float $walletApplied, float $total, float $amountDueNow): array
    {
        $rows = [
            ['key' => 'subtotal', 'label' => 'Subtotal', 'amount' => round($subtotal, 2)],
            ['key' => 'shipping', 'label' => 'Shipping', 'amount' => round($shipping, 2)],
        ];

        if ($promoDiscount > 0) {
            $rows[] = ['key' => 'promo', 'label' => 'Promo discount', 'amount' => -round($promoDiscount, 2)];
        }

        if ($walletApplied > 0) {
            $rows[] = ['key' => 'wallet', 'label' => 'Wallet applied', 'amount' => -round($walletApplied, 2)];
        }

        $rows[] = ['key' => 'total', 'label' => 'Total', 'amount' => round($total, 2)];
        $rows[] = ['key' => 'due_now', 'label' => 'Due now', 'amount' => round($amountDueNow, 2)];

        return $rows;
    }

    private function formatMoney(float $amount, string $currency = 'USD'): string
    {
        return '$' . number_format($amount, 2);
    }

    private function formatSignedMoney(float $amount, string $currency = 'USD'): string
    {
        $abs = '$' . number_format(abs($amount), 2);
        return $amount < 0 ? '-' . $abs : $abs;
    }

    private function shortAddress(Address $address): string
    {
        $text = trim((string) ($address->address_line ?? $address->street_address ?? ''));
        if ($text === '') {
            return '';
        }

        return Str::limit($text, 50, '...');
    }

    private function normalizePromoCode(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    private function buildOrderNumber(): string
    {
        $prefix = $this->shippingConfig->orderNumberPrefix();

        return $prefix . '-' . strtoupper(Str::random(6));
    }
}
