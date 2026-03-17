<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTopUpPayment;
use App\Services\Payments\SquareService;
use App\Services\Payments\PaymentReferenceGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        ]);

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
        );

        $amount = round((float) $validated['amount'], 2);
        $currency = 'USD';

        $topUp = DB::transaction(function () use ($request, $wallet, $amount, $currency, $validated) {
            $reference = app(PaymentReferenceGenerator::class)->generate();
            $idempotencyKey = $reference;

            return WalletTopUpPayment::create([
                'user_id' => $request->user()->id,
                'wallet_id' => $wallet->id,
                'provider' => 'square',
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

        $square = app(SquareService::class);
        $session = $square->createWalletTopUpCheckoutSession($topUp);

        if (! empty($session['square_order_id'])) {
            $topUp->update(['provider_order_id' => $session['square_order_id']]);
        }

        return response()->json([
            'message' => 'Top-up payment created',
            'status' => 201,
            'top_up' => [
                'id' => (string) $topUp->id,
                'reference' => $topUp->reference,
                'provider' => $topUp->provider,
                'amount' => (float) $topUp->amount,
                'currency' => $topUp->currency,
                'checkout_url' => $session['checkout_url'],
                'payment_status' => $topUp->status,
            ],
        ], 201);
    }
}
