<?php

namespace App\Services\Payments;

use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Cart\RemoveOrderedCartItemsService;
use App\Services\PurchaseAssistant\PurchaseAssistantRequestStatusSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Pays an order's current amount_due from wallet using the same completion path as gateway webhooks.
 */
class OrderWalletPaymentService
{
    public function __construct(
        protected PaymentEligibilityService $eligibilityService,
        protected PaymentReferenceGenerator $referenceGenerator
    ) {}

    /**
     * Settle full remaining order balance from wallet.
     *
     * @return array{payment: Payment, order: Order}|array{error_response: JsonResponse}
     */
    public function settleOrderWithWallet(User $user, Order $order): array
    {
        $check = $this->eligibilityService->checkOrderEligibility($order);
        if (! $check['eligible']) {
            return ['error_response' => response()->json([
                'message' => $check['message'],
                'error_key' => $check['error_key'],
                'errors' => [],
                'status' => 422,
            ], 422)];
        }

        $amount = $this->resolveAmountDue($order);
        if ($amount <= 0.00001) {
            return ['error_response' => response()->json([
                'message' => 'Nothing to pay.',
                'error_key' => 'nothing_to_pay',
                'errors' => [],
                'status' => 422,
            ], 422)];
        }

        try {
            return DB::transaction(function () use ($user, $order, $amount) {
                $order = Order::whereKey($order->id)->lockForUpdate()->first();
                if ($order === null) {
                    return ['error_response' => response()->json(['message' => 'Order not found.', 'status' => 404], 404)];
                }

                $check = $this->eligibilityService->checkOrderEligibility($order);
                if (! $check['eligible']) {
                    return ['error_response' => response()->json([
                        'message' => $check['message'],
                        'error_key' => $check['error_key'],
                        'errors' => [],
                        'status' => 422,
                    ], 422)];
                }

                $amount = $this->resolveAmountDue($order);
                if ($amount <= 0.00001) {
                    return ['error_response' => response()->json([
                        'message' => 'Nothing to pay.',
                        'error_key' => 'nothing_to_pay',
                        'errors' => [],
                        'status' => 422,
                    ], 422)];
                }

                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $user->id],
                    ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
                );
                $wallet = Wallet::whereKey($wallet->id)->lockForUpdate()->first();
                if ($wallet === null || (float) $wallet->available_balance + 0.00001 < $amount) {
                    $balance = round((float) ($wallet?->available_balance ?? 0), 2);
                    $shortfall = round(max(0, $amount - $balance), 2);

                    return ['error_response' => response()->json([
                        'message' => 'Insufficient wallet balance.',
                        'error_key' => 'insufficient_wallet_balance',
                        'error_code' => 'insufficient_wallet_balance',
                        'wallet_balance' => $balance,
                        'payable_now_total' => $amount,
                        'required_top_up_amount' => $shortfall,
                        'suggested_top_up_amount' => $shortfall,
                        'errors' => [],
                        'status' => 422,
                    ], 422)];
                }

                $wallet->available_balance = max(0, (float) $wallet->available_balance - $amount);
                $wallet->save();

                $reference = $this->referenceGenerator->generate();
                $currency = $order->currency ?? 'USD';

                $prevWalletApplied = round((float) ($order->wallet_applied_amount ?? 0), 2);

                $payment = Payment::create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'provider' => 'wallet',
                    'currency' => $currency,
                    'amount' => $amount,
                    'status' => PaymentStatus::Paid,
                    'reference' => $reference,
                    'idempotency_key' => 'wallet_order_pay_'.$order->id.'_'.$reference,
                    'paid_at' => now(),
                    'metadata' => [
                        'payment_method' => 'wallet',
                        'source' => 'order_start_payment',
                    ],
                ]);

                $updated = Order::where('id', $order->id)
                    ->where('status', Order::STATUS_PENDING_PAYMENT)
                    ->update([
                        'status' => Order::STATUS_PAID,
                        'placed_at' => now(),
                        'wallet_applied_amount' => round($prevWalletApplied + $amount, 2),
                        'amount_due_now' => 0,
                    ]);

                if ($updated === 0) {
                    throw new \RuntimeException('Order state changed during wallet payment.');
                }

                $order = $order->fresh();

                $wallet->transactions()->create([
                    'type' => 'payment',
                    'title' => 'Order #'.($order->order_number ?? $order->id),
                    'amount' => -$amount,
                    'subtitle' => 'WALLET',
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                ]);

                app(PurchaseAssistantRequestStatusSync::class)->onOrderMarkedPaid($order);
                (app(RemoveOrderedCartItemsService::class))((int) $order->id);

                return [
                    'payment' => $payment->fresh(),
                    'order' => $order->fresh(),
                ];
            });
        } catch (\RuntimeException) {
            return ['error_response' => response()->json([
                'message' => 'Could not complete wallet payment. Please try again.',
                'error_key' => 'wallet_payment_failed',
                'errors' => [],
                'status' => 422,
            ], 422)];
        }
    }

    private function resolveAmountDue(Order $order): float
    {
        $due = (float) ($order->amount_due_now ?? 0);

        return round($due > 0 ? $due : (float) ($order->order_total_snapshot ?? $order->total_amount ?? 0), 2);
    }
}
