<?php

namespace App\Services\Wallet;

use App\Models\Wallet;
use App\Models\WalletWithdrawal;
use Illuminate\Support\Facades\DB;

/**
 * Deducts wallet when a bank withdrawal is marked transferred.
 */
class WalletWithdrawalProcessor
{
    public function markTransferredAndDebit(WalletWithdrawal $withdrawal, ?string $transferProofPath): void
    {
        if ($withdrawal->status === WalletWithdrawal::STATUS_TRANSFERRED) {
            throw new \RuntimeException('Already transferred.');
        }
        if ($withdrawal->status === WalletWithdrawal::STATUS_REJECTED) {
            throw new \RuntimeException('Withdrawal was rejected.');
        }
        if ($withdrawal->status !== WalletWithdrawal::STATUS_APPROVED) {
            throw new \RuntimeException('Withdrawal must be approved before marking as transferred.');
        }

        $proof = is_string($transferProofPath) ? trim($transferProofPath) : '';
        if ($proof === '') {
            throw new \RuntimeException('Transfer proof is required.');
        }

        DB::transaction(function () use ($withdrawal, $proof) {
            $withdrawal->refresh();
            if ($withdrawal->status === WalletWithdrawal::STATUS_TRANSFERRED) {
                throw new \RuntimeException('Already transferred.');
            }

            $gross = round((float) $withdrawal->amount, 2);
            if ($gross <= 0) {
                throw new \RuntimeException('Invalid amount.');
            }

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $withdrawal->user_id],
                ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
            );
            $wallet = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ((float) $wallet->available_balance + 0.00001 < $gross) {
                throw new \RuntimeException('Insufficient wallet balance to complete withdrawal.');
            }

            $wallet->available_balance = round(max(0, (float) $wallet->available_balance - $gross), 2);
            $wallet->save();

            $net = number_format((float) $withdrawal->net_amount, 2);
            $wallet->transactions()->create([
                'type' => 'withdraw_out',
                'title' => 'Withdraw to bank',
                'amount' => -$gross,
                'subtitle' => 'NET ≈ $'.$net.' · FEE $'.number_format((float) $withdrawal->fee_amount, 2),
                'reference_type' => 'wallet_withdrawal',
                'reference_id' => $withdrawal->id,
            ]);

            $withdrawal->transfer_proof = $proof;
            $withdrawal->status = WalletWithdrawal::STATUS_TRANSFERRED;
            $withdrawal->transferred_at = now();
            $withdrawal->save();
        });
    }
}
