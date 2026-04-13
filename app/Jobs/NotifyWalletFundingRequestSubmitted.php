<?php

namespace App\Jobs;

use App\Models\WalletTopupRequest;
use App\Services\Wallet\WalletFundingNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Pushes / in-app notifications for a manual wallet funding request (Wire/Zelle).
 * Queued so the API can return JSON immediately after the row exists (FCM is slow).
 */
class NotifyWalletFundingRequestSubmitted implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $walletTopupRequestId) {}

    public function handle(WalletFundingNotifier $notifier): void
    {
        $t0 = microtime(true);
        $req = WalletTopupRequest::query()->find($this->walletTopupRequestId);
        if ($req === null) {
            Log::warning('wallet_funding.notify_job', [
                'phase' => 'missing_row',
                'wallet_topup_request_id' => $this->walletTopupRequestId,
            ]);

            return;
        }

        Log::info('wallet_funding.notify_job', [
            'phase' => 'notify_start',
            'wallet_topup_request_id' => $this->walletTopupRequestId,
            'ms_since_job_start' => 0,
        ]);

        $notifier->notifySubmitted($req);

        Log::info('wallet_funding.notify_job', [
            'phase' => 'notify_end',
            'wallet_topup_request_id' => $this->walletTopupRequestId,
            'ms_total' => (int) round((microtime(true) - $t0) * 1000),
        ]);
    }
}
