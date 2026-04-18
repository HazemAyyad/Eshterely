<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletTopupRequest;
use App\Models\WalletTopUpPayment;
use App\Services\Payments\PaymentReferenceGenerator;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class WalletController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
        );

        // Pending = total amount of Wire/Zelle manual funding requests awaiting review (not spendable).
        $manualFundingPending = (float) WalletTopupRequest::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [
                WalletTopupRequest::STATUS_PENDING,
                WalletTopupRequest::STATUS_UNDER_REVIEW,
            ])
            ->whereIn('method', [
                WalletTopupRequest::METHOD_WIRE,
                WalletTopupRequest::METHOD_ZELLE,
            ])
            ->sum('amount');

        return response()->json([
            'available' => (float) $wallet->available_balance,
            'pending' => round($manualFundingPending, 2),
            'promo' => (float) $wallet->promo_balance,
            // Legacy DB column (unused in most flows); kept for debugging/admin tools.
            'wallet_pending_balance' => (float) $wallet->pending_balance,
        ]);
    }

    /**
     * Recent Stripe wallet top-ups (saved-card + hosted checkout) for history / pending visibility.
     * GET /api/wallet/stripe-top-ups
     */
    public function stripeTopUps(Request $request): JsonResponse
    {
        $rows = WalletTopUpPayment::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', 'stripe')
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        return response()->json([
            'top_ups' => $rows->map(static function (WalletTopUpPayment $r) {
                $meta = is_array($r->metadata) ? $r->metadata : [];
                $savedCardId = $meta['saved_payment_method_id'] ?? null;

                return [
                    'id' => (string) $r->id,
                    'reference' => $r->reference,
                    'amount' => (float) $r->amount,
                    'currency' => $r->currency,
                    'status' => $r->status,
                    'method' => is_numeric($savedCardId) ? 'saved_card' : 'checkout',
                    'created_at' => $r->created_at?->toIso8601String(),
                    'paid_at' => $r->paid_at?->toIso8601String(),
                    'failure_message' => $r->failure_message,
                ];
            }),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
        );

        $type = $request->query('type', 'all');
        $query = $wallet->transactions();

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        $txs = $query->orderByDesc('created_at')->limit(50)->get();

        $orderIds = $txs
            ->filter(fn ($t) => ($t->reference_type ?? '') === 'order' && $t->reference_id !== null)
            ->map(fn ($t) => (int) $t->reference_id)
            ->unique()
            ->values();

        $ordersById = $orderIds->isNotEmpty()
            ? Order::query()->whereIn('id', $orderIds)->get()->keyBy('id')
            : collect();

        return response()->json($txs->map(fn ($t) => [
            'id' => (string) $t->id,
            'type' => $t->type,
            'title' => $t->title,
            'date_time' => $t->created_at->format('M j, Y • H:i'),
            'amount' => ($t->amount >= 0 ? '+' : '-') . ' $' . number_format(abs($t->amount), 2),
            'subtitle' => $t->subtitle ?? '',
            'is_credit' => $t->amount >= 0,
            'reference_type' => $t->reference_type,
            'reference_id' => $t->reference_id !== null ? (string) $t->reference_id : null,
            'flow' => $this->resolveWalletTransactionFlow($t, $ordersById),
        ]));
    }

    /**
     * UI-oriented classification for wallet history (distinct from generic payment rows).
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Order>  $ordersById
     */
    private function resolveWalletTransactionFlow(object $t, $ordersById): string
    {
        $rt = (string) ($t->reference_type ?? '');

        if ($rt === 'shipment') {
            return 'shipment_payment';
        }

        if ($rt === 'order' && $t->reference_id !== null) {
            $order = $ordersById->get((int) $t->reference_id);

            return ($order && $order->purchase_assistant_request_id)
                ? 'purchase_assistant_payment'
                : 'order_payment';
        }

        if (in_array($rt, ['wallet_top_up_payment', 'wallet_topup_request'], true)) {
            return 'wallet_topup';
        }

        if ($rt === 'wallet_refund') {
            return 'wallet_refund';
        }

        if ($rt === 'wallet_withdrawal') {
            return 'withdrawal';
        }

        if ($rt === 'admin_adjustment') {
            return 'admin_adjustment';
        }

        if ($rt === 'saved_payment_method') {
            return 'card_verification';
        }

        return 'other';
    }

    public function topUp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'nullable|string',
            'gateway' => ['nullable', 'string', 'in:square,stripe'],
        ]);

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
        );

        $amount = round((float) $validated['amount'], 2);
        $currency = 'USD';

        $gatewayManager = app(PaymentGatewayManager::class);
        $requested = isset($validated['gateway']) ? trim((string) $validated['gateway']) : '';
        try {
            $gateway = $requested !== ''
                ? $gatewayManager->resolve($requested)
                : $gatewayManager->resolveDefault();
        } catch (InvalidArgumentException) {
            return response()->json([
                'message' => 'Selected payment gateway is not available.',
                'error_key' => 'gateway_unavailable',
            ], 422);
        }

        $topUp = DB::transaction(function () use ($request, $wallet, $amount, $currency, $validated, $gateway): WalletTopUpPayment {
            $reference = app(PaymentReferenceGenerator::class)->generate();
            $idempotencyKey = $reference;

            return WalletTopUpPayment::create([
                'user_id' => $request->user()->id,
                'wallet_id' => $wallet->id,
                'provider' => $gateway->gatewayCode(),
                'currency' => $currency,
                'amount' => $amount,
                'status' => 'pending',
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'metadata' => array_filter([
                    'payment_method' => $validated['payment_method'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''),
            ]);
        });

        try {
            $session = $gateway->createWalletTopUpCheckoutSession($topUp);
        } catch (Throwable $e) {
            Log::warning('Wallet top-up checkout session failed', [
                'top_up_id' => $topUp->id,
                'gateway' => $gateway->gatewayCode(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not start payment. Check payment gateway settings (admin → payment gateways) and try again.',
                'error_key' => 'checkout_session_failed',
                'detail' => config('app.debug') ? $e->getMessage() : null,
            ], 422);
        }

        $checkoutUrl = is_string($session['checkout_url'] ?? null) ? trim((string) $session['checkout_url']) : '';
        if ($checkoutUrl === '') {
            return response()->json([
                'message' => 'Payment gateway did not return a checkout URL.',
                'error_key' => 'missing_checkout_url',
            ], 422);
        }

        if (! empty($session['provider_order_id'])) {
            $topUp->update(['provider_order_id' => $session['provider_order_id']]);
        }
        if (! empty($session['provider_payment_id'])) {
            $topUp->update(['provider_payment_id' => $session['provider_payment_id']]);
        }

        return response()->json([
            'message' => 'Top-up payment created',
            'status' => 201,
            'checkout_url' => $checkoutUrl,
            'gateway' => $gateway->gatewayCode(),
            'top_up' => [
                'id' => (string) $topUp->id,
                'reference' => $topUp->reference,
                'provider' => $topUp->provider,
                'amount' => (float) $topUp->amount,
                'currency' => $topUp->currency,
                'checkout_url' => $checkoutUrl,
                'payment_status' => $topUp->status,
            ],
        ], 201);
    }
}
