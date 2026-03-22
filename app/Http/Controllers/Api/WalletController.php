<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
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

        return response()->json([
            'available' => (float) $wallet->available_balance,
            'pending' => (float) $wallet->pending_balance,
            'promo' => (float) $wallet->promo_balance,
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

        return response()->json($txs->map(fn ($t) => [
            'id' => (string) $t->id,
            'type' => $t->type,
            'title' => $t->title,
            'date_time' => $t->created_at->format('M j, Y • H:i'),
            'amount' => ($t->amount >= 0 ? '+' : '-') . ' $' . number_format(abs($t->amount), 2),
            'subtitle' => $t->subtitle ?? '',
            'is_credit' => $t->amount >= 0,
        ]));
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
