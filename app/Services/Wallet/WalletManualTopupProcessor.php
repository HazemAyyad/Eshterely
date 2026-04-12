<?php

namespace App\Services\Wallet;

use App\Models\Wallet;
use App\Models\WalletTopupRequest;
use Illuminate\Support\Facades\DB;

class WalletManualTopupProcessor
{
    public function __construct(
        protected WalletFundingNotifier $notifier
    ) {}

    public function approve(WalletTopupRequest $request, int $adminId): void
    {
        $didApprove = false;

        DB::transaction(function () use ($request, $adminId, &$didApprove): void {
            /** @var WalletTopupRequest $locked */
            $locked = WalletTopupRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($locked->status === WalletTopupRequest::STATUS_APPROVED) {
                return;
            }

            if (! in_array($locked->status, [WalletTopupRequest::STATUS_PENDING, WalletTopupRequest::STATUS_UNDER_REVIEW], true)) {
                throw new \RuntimeException('Request cannot be approved in its current state.');
            }

            $meta = $locked->metadata ?? [];
            if (! empty($meta['wallet_credited_at'])) {
                throw new \RuntimeException('This request was already credited.');
            }

            $wallet = Wallet::query()->lockForUpdate()->firstOrCreate(
                ['user_id' => $locked->user_id],
                ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
            );

            $amount = (float) $locked->amount;
            $wallet->available_balance = (float) $wallet->available_balance + $amount;
            $wallet->save();

            $txType = $locked->method === WalletTopupRequest::METHOD_ZELLE
                ? 'zelle_credit'
                : 'wire_transfer_credit';

            $title = $locked->method === WalletTopupRequest::METHOD_ZELLE
                ? 'Zelle deposit'
                : 'Wire transfer deposit';

            $wallet->transactions()->create([
                'type' => $txType,
                'title' => $title,
                'amount' => $amount,
                'subtitle' => 'APPROVED',
                'reference_type' => 'wallet_topup_request',
                'reference_id' => $locked->id,
            ]);

            $meta['wallet_credited_at'] = now()->toIso8601String();
            $locked->status = WalletTopupRequest::STATUS_APPROVED;
            $locked->reviewed_by = $adminId;
            $locked->reviewed_at = now();
            $locked->approved_at = now();
            $locked->metadata = $meta;
            $locked->save();
            $didApprove = true;
        });

        if ($didApprove) {
            $this->notifier->notifyApproved($request->fresh());
        }
    }

    public function reject(WalletTopupRequest $request, int $adminId, ?string $adminNotes): void
    {
        DB::transaction(function () use ($request, $adminId, $adminNotes): void {
            /** @var WalletTopupRequest $locked */
            $locked = WalletTopupRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (in_array($locked->status, [WalletTopupRequest::STATUS_APPROVED, WalletTopupRequest::STATUS_REJECTED], true)) {
                throw new \RuntimeException('Request is already finalized.');
            }

            $locked->status = WalletTopupRequest::STATUS_REJECTED;
            $locked->reviewed_by = $adminId;
            $locked->reviewed_at = now();
            if ($adminNotes !== null && $adminNotes !== '') {
                $locked->admin_notes = $adminNotes;
            }
            $locked->save();
        });

        $this->notifier->notifyRejected($request->fresh());
    }
}
