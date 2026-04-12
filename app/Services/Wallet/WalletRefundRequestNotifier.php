<?php

namespace App\Services\Wallet;

use App\Models\Notification;
use App\Models\WalletRefundRequest;
use App\Services\Fcm\FcmNotificationService;
use App\Services\Fcm\NotificationDispatchService;

/**
 * In-app notification row + FCM for wallet refund request lifecycle.
 */
class WalletRefundRequestNotifier
{
    public function __construct(
        protected NotificationDispatchService $dispatchService
    ) {}

    public function notifySubmitted(WalletRefundRequest $req): void
    {
        $this->dispatch($req, 'submitted');
    }

    public function notifyApproved(WalletRefundRequest $req): void
    {
        $this->dispatch($req, 'approved');
    }

    public function notifyRejected(WalletRefundRequest $req): void
    {
        $this->dispatch($req, 'rejected');
    }

    public function notifyTransferred(WalletRefundRequest $req): void
    {
        $this->dispatch($req, 'transferred');
    }

    private function dispatch(WalletRefundRequest $req, string $kind): void
    {
        $req->loadMissing('user');
        $user = $req->user;
        if ($user === null) {
            return;
        }

        $amt = number_format((float) $req->amount, 2);
        [$title, $body] = match ($kind) {
            'submitted' => [
                'Refund request received',
                "We received your refund request for \${$amt}. Our team will review it soon.",
            ],
            'approved' => [
                'Refund request approved',
                "Your refund request #{$req->id} for \${$amt} has been approved. Bank processing will follow.",
            ],
            'rejected' => [
                'Refund request not approved',
                "Your refund request #{$req->id} was not approved. Open the app for details.",
            ],
            'transferred' => [
                'Refund transfer completed',
                'Your bank transfer for this refund has been marked as sent. Receiving the funds may take up to 30 days depending on your bank.',
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
            'wallet_refund',
            (string) $req->id,
            $title,
            $body,
            'wallet_refund',
            (string) $req->id,
            'wallet',
            ['refund_request_id' => $req->id]
        );

        $this->dispatchService->sendSystemEvent($title, $body, $user, $data);
    }
}
