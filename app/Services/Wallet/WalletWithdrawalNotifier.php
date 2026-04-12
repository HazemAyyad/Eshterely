<?php

namespace App\Services\Wallet;

use App\Models\Notification;
use App\Models\WalletWithdrawal;
use App\Services\Fcm\FcmNotificationService;
use App\Services\Fcm\NotificationDispatchService;

class WalletWithdrawalNotifier
{
    public function __construct(
        protected NotificationDispatchService $dispatchService
    ) {}

    public function notifySubmitted(WalletWithdrawal $w): void
    {
        $this->dispatch($w, 'submitted');
    }

    public function notifyApproved(WalletWithdrawal $w): void
    {
        $this->dispatch($w, 'approved');
    }

    public function notifyRejected(WalletWithdrawal $w): void
    {
        $this->dispatch($w, 'rejected');
    }

    public function notifyTransferred(WalletWithdrawal $w): void
    {
        $this->dispatch($w, 'transferred');
    }

    private function dispatch(WalletWithdrawal $w, string $kind): void
    {
        $w->loadMissing('user');
        $user = $w->user;
        if ($user === null) {
            return;
        }

        $gross = number_format((float) $w->amount, 2);
        $net = number_format((float) $w->net_amount, 2);

        [$title, $body] = match ($kind) {
            'submitted' => [
                'Withdrawal request received',
                "We received your request to withdraw \${$gross} to your bank (net ≈ \${$net} after fees). Our team will review it.",
            ],
            'approved' => [
                'Withdrawal approved',
                "Your withdrawal request #{$w->id} for \${$gross} was approved. Transfer will follow.",
            ],
            'rejected' => [
                'Withdrawal not approved',
                "Your withdrawal request #{$w->id} was not approved. Open the app for details.",
            ],
            'transferred' => [
                'Transfer completed',
                'Your bank transfer has been sent. Funds may take up to 30 days to arrive depending on your bank.',
            ],
            default => [null, null],
        };

        if ($title === null || $body === null) {
            return;
        }

        Notification::create([
            'user_id' => $user->id,
            'type' => 'wallet',
            'title' => $title,
            'subtitle' => $body,
            'read' => false,
            'important' => true,
            'action_label' => 'Wallet',
            'action_route' => '/wallet',
        ]);

        $data = FcmNotificationService::systemEventData(
            'wallet_withdrawal',
            (string) $w->id,
            $title,
            $body,
            'wallet_withdrawal',
            (string) $w->id,
            'wallet',
            ['wallet_withdrawal_id' => $w->id]
        );

        $this->dispatchService->sendSystemEvent($title, $body, $user, $data);
    }
}
