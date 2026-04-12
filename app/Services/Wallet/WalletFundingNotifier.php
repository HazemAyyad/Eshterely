<?php

namespace App\Services\Wallet;

use App\Models\Notification;
use App\Models\WalletTopupRequest;
use App\Services\Fcm\FcmNotificationService;
use App\Services\Fcm\NotificationDispatchService;

class WalletFundingNotifier
{
    public function __construct(
        protected NotificationDispatchService $dispatchService
    ) {}

    public function notifySubmitted(WalletTopupRequest $req): void
    {
        $this->dispatch($req, 'submitted');
    }

    public function notifyApproved(WalletTopupRequest $req): void
    {
        $this->dispatch($req, 'approved');
    }

    public function notifyRejected(WalletTopupRequest $req): void
    {
        $this->dispatch($req, 'rejected');
    }

    private function dispatch(WalletTopupRequest $req, string $kind): void
    {
        $req->loadMissing('user');
        $user = $req->user;
        if ($user === null) {
            return;
        }

        $amt = number_format((float) $req->amount, 2);
        $methodLabel = $req->method === WalletTopupRequest::METHOD_ZELLE ? 'Zelle' : 'Wire transfer';

        [$title, $body] = match ($kind) {
            'submitted' => [
                "{$methodLabel} deposit request received",
                "We received your request to add \${$amt} via {$methodLabel}. Our team will review it.",
            ],
            'approved' => [
                'Wallet funded',
                "Your {$methodLabel} request for \${$amt} was approved and added to your wallet.",
            ],
            'rejected' => [
                'Funding request not approved',
                "Your {$methodLabel} request #{$req->id} was not approved. Open the app for details.",
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
            'wallet_manual_topup',
            (string) $req->id,
            $title,
            $body,
            'wallet_manual_topup',
            (string) $req->id,
            'wallet',
            ['wallet_topup_request_id' => $req->id]
        );

        $this->dispatchService->sendSystemEvent($title, $body, $user, $data);
    }
}
