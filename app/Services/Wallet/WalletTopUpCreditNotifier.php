<?php

namespace App\Services\Wallet;

use App\Models\Notification;
use App\Models\WalletTopUpPayment;
use App\Models\User;
use App\Services\Fcm\FcmNotificationService;
use App\Services\Fcm\NotificationDispatchService;

/**
 * Fires when wallet balance increases from a Stripe (or other) top-up payment webhook.
 */
class WalletTopUpCreditNotifier
{
    public function __construct(
        protected NotificationDispatchService $dispatchService
    ) {}

    public function notifyCredited(WalletTopUpPayment $topUp): void
    {
        $user = User::query()->find($topUp->user_id);
        if ($user === null) {
            return;
        }

        $amt = number_format((float) $topUp->amount, 2);
        $title = 'Wallet credited';
        $body = "\${$amt} was added to your wallet.";

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
            'wallet_top_up',
            (string) $topUp->id,
            $title,
            $body,
            'wallet_top_up',
            (string) $topUp->id,
            'wallet',
            ['wallet_top_up_payment_id' => $topUp->id]
        );

        $this->dispatchService->sendSystemEvent($title, $body, $user, $data);
    }
}
