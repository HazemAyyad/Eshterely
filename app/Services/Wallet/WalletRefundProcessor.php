<?php

namespace App\Services\Wallet;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\Wallet;
use App\Models\WalletRefund;
use Illuminate\Support\Facades\DB;

/**
 * Credits wallet when an order/shipment refund is approved.
 */
class WalletRefundProcessor
{
    public function approveAndCredit(WalletRefund $refund, int $adminId): void
    {
        if ($refund->status !== WalletRefund::STATUS_PENDING) {
            throw new \RuntimeException('Refund is not pending.');
        }

        DB::transaction(function () use ($refund, $adminId) {
            $refund->refresh();
            if ($refund->status !== WalletRefund::STATUS_PENDING) {
                throw new \RuntimeException('Refund is not pending.');
            }

            $amount = round((float) $refund->amount, 2);
            if ($amount <= 0) {
                throw new \RuntimeException('Invalid amount.');
            }

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $refund->user_id],
                ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
            );
            $wallet = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $wallet->available_balance = round((float) $wallet->available_balance + $amount, 2);
            $wallet->save();

            [$title, $subtitle] = $this->labels($refund);

            $wallet->transactions()->create([
                'type' => 'refund_in',
                'title' => $title,
                'amount' => $amount,
                'subtitle' => $subtitle,
                'reference_type' => 'wallet_refund',
                'reference_id' => $refund->id,
            ]);

            $refund->status = WalletRefund::STATUS_APPROVED;
            $refund->reviewed_by = $adminId;
            $refund->reviewed_at = now();
            $refund->save();
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function labels(WalletRefund $refund): array
    {
        if ($refund->source_type === WalletRefund::SOURCE_ORDER) {
            $order = Order::find($refund->source_id);
            $num = $order?->order_number ?? (string) $refund->source_id;

            return ["Refund to wallet · Order #{$num}", 'ORDER'];
        }

        if ($refund->source_type === WalletRefund::SOURCE_SHIPMENT) {
            return ['Refund to wallet · Shipment #'.$refund->source_id, 'SHIPMENT'];
        }

        return ['Refund to wallet', ''];
    }
}
