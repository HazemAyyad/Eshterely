<?php

namespace App\Services\Wallet;

use App\Models\Notification;
use App\Models\WalletRefund;
use App\Services\Fcm\FcmNotificationService;
use App\Services\Fcm\NotificationDispatchService;

class WalletRefundNotifier
{
    public function __construct(
        protected NotificationDispatchService $dispatchService
    ) {}

    public function notifySubmitted(WalletRefund $req): void
    {
        $this->dispatch($req, 'submitted');
    }

    public function notifyApproved(WalletRefund $req): void
    {
        $this->dispatch($req, 'approved');
    }

    public function notifyRejected(WalletRefund $req): void
    {
        $this->dispatch($req, 'rejected');
    }

    private function dispatch(WalletRefund $req, string $kind): void
    {
        $req->loadMissing('user');
        $user = $req->user;
        if ($user === null) {
            return;
        }

        $amt = number_format((float) $req->amount, 2);
        $src = $req->source_type === WalletRefund::SOURCE_ORDER ? 'order' : 'shipment';
        [$title, $body] = match ($kind) {
            'submitted' => [
                'Refund to wallet requested',
                "We received your {$src} refund request for \${$amt}. Our team will review it.",
            ],
            'approved' => [
                'Refund to wallet approved',
                "Your {$src} refund of \${$amt} was approved and added to your wallet balance.",
            ],
            'rejected' => [
                'Refund to wallet not approved',
                "Your {$src} refund request #{$req->id} was not approved. Open the app for details.",
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
            'wallet_refund_to_wallet',
            (string) $req->id,
            $title,
            $body,
            'wallet_refund_to_wallet',
            (string) $req->id,
            'wallet',
            ['wallet_refund_id' => $req->id]
        );

        $this->dispatchService->sendSystemEvent($title, $body, $user, $data);
    }
}
