<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTopUpPayment;
use App\Services\Activity\UserActivityLogger;
use App\Support\UserActivityAction;
use Illuminate\Support\Facades\DB;

/**
 * Credits wallet + creates wallet_transaction for a paid {@link WalletTopUpPayment} exactly once.
 * Used by Stripe webhooks and by the saved-card top-up API when PaymentIntent succeeds synchronously.
 */
class WalletTopUpPaymentCompletionService
{
    public function __construct(
        protected UserActivityLogger $activityLogger
    ) {}

    /**
     * Idempotent wallet credit when the top-up row is already (or just became) `paid`.
     *
     * @return  bool True when this run performed the credit (for FCM / notifications).
     */
    public function creditWalletIfPaidAndNotYetCredited(WalletTopUpPayment $topUp): bool
    {
        $topUp->refresh();
        if ($topUp->status !== 'paid') {
            return false;
        }

        $meta = is_array($topUp->metadata) ? $topUp->metadata : [];
        if (isset($meta['wallet_credited_at'])) {
            return false;
        }

        $wallet = Wallet::lockForUpdate()->find($topUp->wallet_id);
        if ($wallet === null) {
            return false;
        }

        $wallet->available_balance = (float) $wallet->available_balance + (float) $topUp->amount;
        $wallet->save();

        $txType = ($meta['wallet_tx_type'] ?? '') === 'card_topup_credit'
            ? 'card_topup_credit'
            : 'top_up';
        $title = $txType === 'card_topup_credit' ? 'Card top-up' : 'Top-up';
        $subtitle = $txType === 'card_topup_credit' ? 'SAVED CARD' : 'PAID';

        $wallet->transactions()->create([
            'type' => $txType,
            'title' => $title,
            'amount' => (float) $topUp->amount,
            'subtitle' => $subtitle,
            'reference_type' => 'wallet_top_up_payment',
            'reference_id' => $topUp->id,
        ]);

        $meta['wallet_credited_at'] = now()->toIso8601String();
        $topUp->update(['metadata' => $meta]);

        $user = User::find($wallet->user_id);
        if ($user !== null) {
            $this->activityLogger->log(
                $user,
                UserActivityAction::WALLET_TOPUP,
                'Wallet top-up +'.number_format((float) $topUp->amount, 2).' '.$topUp->currency,
                null,
                [
                    'wallet_top_up_payment_id' => $topUp->id,
                    'amount' => round((float) $topUp->amount, 2),
                    'currency' => $topUp->currency,
                ],
                null
            );
        }

        return true;
    }

    /**
     * Saved-card top-up: Stripe confirms charge immediately (`confirm: true`). Credit wallet here
     * so the app works without relying on webhooks (e.g. local dev).
     */
    public function settleSavedCardTopUpIfIntentSucceeded(
        WalletTopUpPayment $topUp,
        string $stripePaymentIntentStatus
    ): bool {
        if (strtolower(trim($stripePaymentIntentStatus)) !== 'succeeded') {
            return false;
        }

        $credited = false;

        DB::transaction(function () use ($topUp, &$credited): void {
            $row = WalletTopUpPayment::lockForUpdate()->find($topUp->id);
            if ($row === null) {
                return;
            }
            if (in_array($row->status, ['failed', 'cancelled'], true)) {
                return;
            }

            if ($row->status !== 'paid') {
                $row->update([
                    'status' => 'paid',
                    'paid_at' => $row->paid_at ?? now(),
                    'failure_code' => null,
                    'failure_message' => null,
                ]);
            }

            $credited = $this->creditWalletIfPaidAndNotYetCredited($row->fresh());
        });

        if ($credited) {
            DB::afterCommit(function () use ($topUp): void {
                app(WalletTopUpCreditNotifier::class)->notifyCredited(
                    WalletTopUpPayment::query()->findOrFail($topUp->id)
                );
            });
        }

        return $credited;
    }
}
