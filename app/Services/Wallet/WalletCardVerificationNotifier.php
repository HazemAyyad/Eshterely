<?php

namespace App\Services\Wallet;

use App\Models\Notification;
use App\Models\SavedPaymentMethod;
use App\Services\Fcm\FcmNotificationService;
use App\Services\Fcm\NotificationDispatchService;

class WalletCardVerificationNotifier
{
    public function __construct(
        protected NotificationDispatchService $dispatchService
    ) {}

    public function notifyVerified(SavedPaymentMethod $card): void
    {
        $this->dispatch($card, 'verified');
    }

    public function notifyFailed(SavedPaymentMethod $card): void
    {
        $this->dispatch($card, 'failed');
    }

    private function dispatch(SavedPaymentMethod $card, string $kind): void
    {
        $card->loadMissing('user');
        $user = $card->user;
        if ($user === null) {
            return;
        }

        $last4 = $card->last4 ?? '****';
        [$title, $body] = match ($kind) {
            'verified' => [
                'Card verified',
                "Your card ending in {$last4} is verified. The verification amount was added to your wallet.",
            ],
            'failed' => $card->verification_status === SavedPaymentMethod::STATUS_BLOCKED
                ? [
                    'Card verification blocked',
                    "Your card ending in {$last4} was blocked after too many incorrect attempts. An administrator must review and unblock it before you can try again.",
                ]
                : [
                    'Card verification failed',
                    "We could not verify your card ending in {$last4}. Add a different card or contact support.",
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
            'wallet_card_verification',
            (string) $card->id,
            $title,
            $body,
            'wallet_card_verification',
            (string) $card->id,
            'wallet',
            ['saved_payment_method_id' => $card->id]
        );

        $this->dispatchService->sendSystemEvent($title, $body, $user, $data);
    }
}
