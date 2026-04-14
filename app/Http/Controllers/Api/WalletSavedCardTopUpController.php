<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedPaymentMethod;
use App\Models\Wallet;
use App\Models\WalletTopUpPayment;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentReferenceGenerator;
use App\Services\Wallet\StripeSavedCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WalletSavedCardTopUpController extends Controller
{
    public function __construct(
        protected StripeSavedCardService $stripeSavedCardService,
        protected PaymentGatewayManager $gatewayManager
    ) {}

    public function store(Request $request): JsonResponse
    {
        if (! in_array('stripe', $this->gatewayManager->getEnabledGateways(), true)) {
            return response()->json([
                'message' => 'Card top-up is not available.',
                'error_key' => 'stripe_disabled',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'saved_payment_method_id' => 'required|integer|exists:saved_payment_methods,id',
        ]);

        $user = $request->user();
        $amount = round((float) $validated['amount'], 2);

        /** @var SavedPaymentMethod $card */
        $card = SavedPaymentMethod::query()
            ->where('user_id', $user->id)
            ->whereKey((int) $validated['saved_payment_method_id'])
            ->firstOrFail();

        if (! $card->isUsableForTopUp()) {
            return response()->json([
                'message' => 'Choose a verified card to add funds.',
                'error_key' => 'card_not_verified',
            ], 422);
        }

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
        );

        $topUp = DB::transaction(function () use ($user, $wallet, $amount, $card): WalletTopUpPayment {
            $reference = app(PaymentReferenceGenerator::class)->generate();

            return WalletTopUpPayment::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'provider' => 'stripe',
                'currency' => $this->gatewayManager->stripeCurrencyDefault(),
                'amount' => $amount,
                'status' => 'pending',
                'reference' => $reference,
                'idempotency_key' => $reference,
                'metadata' => [
                    'wallet_tx_type' => 'card_topup_credit',
                    'saved_payment_method_id' => $card->id,
                ],
            ]);
        });

        try {
            $pi = $this->stripeSavedCardService->createTopUpPaymentIntent(
                $user,
                $card,
                $amount,
                $topUp->reference,
                (int) $topUp->id
            );
        } catch (Throwable $e) {
            Log::warning('Saved card wallet top-up PaymentIntent failed', [
                'top_up_id' => $topUp->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'error_key' => 'payment_intent_failed',
            ], 422);
        }

        $topUp->update([
            'provider_payment_id' => $pi['payment_intent_id'],
            'provider_order_id' => (string) $topUp->id,
            'status' => 'processing',
        ]);

        return response()->json([
            'message' => 'Saved card top-up initiated.',
            'payment_intent' => [
                'client_secret' => $pi['client_secret'],
                'payment_intent_id' => $pi['payment_intent_id'],
                'status' => $pi['status'],
            ],
            'top_up' => [
                'id' => (string) $topUp->id,
                'reference' => $topUp->reference,
                'amount' => (float) $topUp->amount,
                'currency' => $topUp->currency,
                'payment_status' => $topUp->fresh()->status,
            ],
        ], 201);
    }
}
