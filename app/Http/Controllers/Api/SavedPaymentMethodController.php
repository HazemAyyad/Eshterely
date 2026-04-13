<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedPaymentMethod;
use App\Models\Wallet;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Wallet\StripeSavedCardService;
use App\Services\Wallet\WalletCardVerificationNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SavedPaymentMethodController extends Controller
{
    public function __construct(
        protected StripeSavedCardService $stripeSavedCardService,
        protected PaymentGatewayManager $gatewayManager,
        protected WalletCardVerificationNotifier $verificationNotifier
    ) {}

    public function setupIntent(Request $request): JsonResponse
    {
        if ($r = $this->ensureStripeEnabled()) {
            return $r;
        }

        try {
            $payload = $this->stripeSavedCardService->createSetupIntent($request->user());
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_key' => 'stripe_setup_failed',
            ], 422);
        }

        return response()->json([
            'setup_intent' => [
                'client_secret' => $payload['client_secret'],
                'setup_intent_id' => $payload['setup_intent_id'],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($r = $this->ensureStripeEnabled()) {
            return $r;
        }

        $validated = $request->validate([
            'setup_intent_id' => 'required|string',
        ]);

        try {
            $result = $this->stripeSavedCardService->completeAddCardFromSetupIntent(
                $request->user(),
                trim($validated['setup_intent_id'])
            );
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_key' => 'add_card_failed',
            ], 422);
        }

        $card = $result['saved_payment_method'];

        return response()->json([
            'saved_payment_method' => $this->serializeCard($card),
            'verification' => [
                'requires_action' => (bool) $result['requires_action'],
                'client_secret' => $result['client_secret'],
            ],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $rows = SavedPaymentMethod::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'saved_payment_methods' => $rows->map(fn (SavedPaymentMethod $c) => $this->serializeCard($c)),
        ]);
    }

    public function verify(Request $request, SavedPaymentMethod $savedPaymentMethod): JsonResponse
    {
        if ($savedPaymentMethod->user_id !== $request->user()->id) {
            abort(404);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:5',
        ]);

        if ($savedPaymentMethod->verification_status !== SavedPaymentMethod::STATUS_PENDING) {
            return response()->json([
                'message' => 'This card is not awaiting verification.',
                'error_key' => 'not_pending_verification',
            ], 422);
        }

        $guess = round((float) $validated['amount'], 2);

        $verified = false;
        $mismatch = false;
        $attemptsAfter = 0;

        DB::transaction(function () use ($savedPaymentMethod, $guess, &$verified, &$mismatch, &$attemptsAfter): void {
            $card = SavedPaymentMethod::query()->lockForUpdate()->findOrFail($savedPaymentMethod->id);
            if ($card->verification_status !== SavedPaymentMethod::STATUS_PENDING) {
                return;
            }

            $expected = round((float) $card->verification_charge_amount, 2);
            $card->increment('verification_attempts');
            $card->refresh();
            $attemptsAfter = (int) $card->verification_attempts;

            if (abs($guess - $expected) > 0.009) {
                $mismatch = true;
                if ($card->verification_attempts >= 5) {
                    $card->verification_status = SavedPaymentMethod::STATUS_FAILED;
                    $card->save();
                }

                return;
            }

            $wallet = Wallet::query()->lockForUpdate()->firstOrCreate(
                ['user_id' => $card->user_id],
                ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
            );

            $credit = (float) $card->verification_charge_amount;
            $wallet->available_balance = (float) $wallet->available_balance + $credit;
            $wallet->save();

            $wallet->transactions()->create([
                'type' => 'card_verification_credit',
                'title' => 'Card verification',
                'amount' => $credit,
                'subtitle' => 'VERIFIED',
                'reference_type' => 'saved_payment_method',
                'reference_id' => $card->id,
            ]);

            $card->verification_status = SavedPaymentMethod::STATUS_VERIFIED;
            $card->verified_at = now();
            $card->save();
            $verified = true;
        });

        if ($mismatch) {
            if ($attemptsAfter >= 5) {
                $this->verificationNotifier->notifyFailed($savedPaymentMethod->fresh());
            }

            return response()->json([
                'message' => 'The amount does not match the verification charge. Check your bank statement.',
                'error_key' => 'verification_mismatch',
                'attempts_remaining' => max(0, 5 - $attemptsAfter),
            ], 422);
        }

        if (! $verified) {
            return response()->json([
                'message' => 'This card is not awaiting verification.',
                'error_key' => 'not_pending_verification',
            ], 422);
        }

        $fresh = $savedPaymentMethod->fresh();
        $this->verificationNotifier->notifyVerified($fresh);

        return response()->json([
            'message' => 'Card verified.',
            'saved_payment_method' => $this->serializeCard($fresh),
        ]);
    }

    public function setDefault(Request $request, SavedPaymentMethod $savedPaymentMethod): JsonResponse
    {
        if ($savedPaymentMethod->user_id !== $request->user()->id) {
            abort(404);
        }

        if ($savedPaymentMethod->verification_status !== SavedPaymentMethod::STATUS_VERIFIED) {
            return response()->json([
                'message' => 'Only verified cards can be set as default.',
                'error_key' => 'card_not_verified',
            ], 422);
        }

        DB::transaction(function () use ($request, $savedPaymentMethod): void {
            SavedPaymentMethod::query()
                ->where('user_id', $request->user()->id)
                ->update(['is_default' => false]);
            $savedPaymentMethod->update(['is_default' => true]);
        });

        return response()->json([
            'saved_payment_method' => $this->serializeCard($savedPaymentMethod->fresh()),
        ]);
    }

    public function destroy(Request $request, SavedPaymentMethod $savedPaymentMethod): JsonResponse
    {
        if ($savedPaymentMethod->user_id !== $request->user()->id) {
            abort(404);
        }

        try {
            $this->stripeSavedCardService->detachPaymentMethod($savedPaymentMethod);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_key' => 'detach_failed',
            ], 422);
        }

        $savedPaymentMethod->delete();

        return response()->json(['message' => 'Card removed.']);
    }

    private function ensureStripeEnabled(): ?JsonResponse
    {
        if (! in_array('stripe', $this->gatewayManager->getEnabledGateways(), true)) {
            return response()->json([
                'message' => 'Card payments are not available.',
                'error_key' => 'stripe_disabled',
            ], 422);
        }

        return null;
    }

    private function serializeCard(SavedPaymentMethod $c): array
    {
        return [
            'id' => (string) $c->id,
            'stripe_payment_method_id' => $c->stripe_payment_method_id,
            'brand' => $c->brand,
            'last4' => $c->last4,
            'exp_month' => $c->exp_month,
            'exp_year' => $c->exp_year,
            'is_default' => (bool) $c->is_default,
            'verification_status' => $c->verification_status,
            'verified_at' => $c->verified_at?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
