<?php

namespace App\Services\PurchaseAssistant;

use App\Models\Notification;
use App\Models\PurchaseAssistantRequest;
use App\Models\User;
use App\Services\Fcm\NotificationDispatchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseAssistantRequestNotifier
{
    public function __construct(
        protected NotificationDispatchService $dispatchService
    ) {}

    /**
     * Notify the customer when status changes from admin or payment webhooks.
     * Skips when old and new are equal or when the transition is not customer-facing.
     */
    public function notifyAfterStatusChange(
        PurchaseAssistantRequest $request,
        User $user,
        ?string $oldStatus,
        string $newStatus
    ): void {
        if ($oldStatus !== null && $oldStatus === $newStatus) {
            return;
        }

        if (! $this->shouldNotifyForNewStatus($newStatus)) {
            return;
        }

        [$title, $body, $actionLabel] = $this->messageForStatus($request, $newStatus);

        $route = '/purchase-assistant-requests/'.$request->id;
        $meta = $this->baseMeta($request, $actionLabel, $route);

        Notification::create([
            'user_id' => $user->id,
            'type' => 'orders',
            'title' => $title,
            'subtitle' => $body,
            'read' => false,
            'important' => true,
            'action_label' => $actionLabel,
            'action_route' => $route,
        ]);

        $this->dispatchService->sendToUser(
            $user,
            $title,
            $body,
            $this->appIconUrl(),
            null,
            $meta,
            null
        );
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function messageForStatus(PurchaseAssistantRequest $request, string $status): array
    {
        $title = $this->appTitle();

        return match ($status) {
            PurchaseAssistantRequest::STATUS_UNDER_REVIEW => [
                $title,
                'We are reviewing your Purchase Assistant request.',
                'view_request',
            ],
            PurchaseAssistantRequest::STATUS_AWAITING_CUSTOMER_PAYMENT => [
                $title,
                'Your Purchase Assistant request is priced. Please complete payment to continue.',
                'pay_now',
            ],
            PurchaseAssistantRequest::STATUS_REJECTED => [
                $title,
                'Your Purchase Assistant request could not be fulfilled.',
                'view_request',
            ],
            PurchaseAssistantRequest::STATUS_CANCELLED => [
                $title,
                'Your Purchase Assistant request was cancelled.',
                'view_request',
            ],
            PurchaseAssistantRequest::STATUS_PAID => [
                $title,
                'Payment received for your Purchase Assistant order.',
                'view_request',
            ],
            PurchaseAssistantRequest::STATUS_COMPLETED => [
                $title,
                'Your Purchase Assistant request is completed.',
                'view_request',
            ],
            PurchaseAssistantRequest::STATUS_PURCHASING => [
                $title,
                'We are purchasing your item for your Purchase Assistant order.',
                'view_request',
            ],
            PurchaseAssistantRequest::STATUS_PURCHASED => [
                $title,
                'Your Purchase Assistant item has been purchased.',
                'view_request',
            ],
            PurchaseAssistantRequest::STATUS_IN_TRANSIT_TO_WAREHOUSE => [
                $title,
                'Your Purchase Assistant shipment is on the way to our warehouse.',
                'view_request',
            ],
            PurchaseAssistantRequest::STATUS_RECEIVED_AT_WAREHOUSE => [
                $title,
                'Your Purchase Assistant item arrived at our warehouse.',
                'view_request',
            ],
            default => [
                $title,
                'Your Purchase Assistant request was updated.',
                'view_request',
            ],
        };
    }

    private function shouldNotifyForNewStatus(string $newStatus): bool
    {
        return in_array($newStatus, [
            PurchaseAssistantRequest::STATUS_UNDER_REVIEW,
            PurchaseAssistantRequest::STATUS_AWAITING_CUSTOMER_PAYMENT,
            PurchaseAssistantRequest::STATUS_REJECTED,
            PurchaseAssistantRequest::STATUS_CANCELLED,
            PurchaseAssistantRequest::STATUS_PAID,
            PurchaseAssistantRequest::STATUS_COMPLETED,
            PurchaseAssistantRequest::STATUS_PURCHASING,
            PurchaseAssistantRequest::STATUS_PURCHASED,
            PurchaseAssistantRequest::STATUS_IN_TRANSIT_TO_WAREHOUSE,
            PurchaseAssistantRequest::STATUS_RECEIVED_AT_WAREHOUSE,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseMeta(PurchaseAssistantRequest $request, string $actionLabel, string $route): array
    {
        $meta = [
            'route_key' => 'purchase_assistant',
            'target_type' => 'purchase_assistant_request',
            'target_id' => (string) $request->id,
            'action_label' => $actionLabel,
            'action_route' => $route,
        ];
        if ($request->converted_order_id !== null) {
            $meta['converted_order_id'] = (string) $request->converted_order_id;
        }

        return $meta;
    }

    private function appTitle(): string
    {
        try {
            if (Schema::hasTable('app_config') && Schema::hasColumn('app_config', 'app_name')) {
                $row = DB::table('app_config')->first();
                $name = $row?->app_name ?? null;
                if (is_string($name) && trim($name) !== '') {
                    return trim($name);
                }
            }
        } catch (\Throwable) {
        }

        return config('app.name', 'Zayer');
    }

    private function appIconUrl(): ?string
    {
        try {
            if (Schema::hasTable('app_config') && Schema::hasColumn('app_config', 'app_icon_url')) {
                $row = DB::table('app_config')->first();
                $u = $row?->app_icon_url ?? null;

                return is_string($u) && $u !== '' ? $u : null;
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
