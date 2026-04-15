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

    public function notifyPaymentReady(PurchaseAssistantRequest $request, User $user): void
    {
        $title = $this->appTitle();
        $body = 'Your Purchase Assistant request is priced. Please complete payment to continue.';
        $route = '/purchase-assistant-requests/'.$request->id;

        Notification::create([
            'user_id' => $user->id,
            'type' => 'orders',
            'title' => $title,
            'subtitle' => $body,
            'read' => false,
            'important' => true,
            'action_label' => 'pay_now',
            'action_route' => $route,
        ]);

        $this->dispatchService->sendToUser(
            $user,
            $title,
            $body,
            $this->appIconUrl(),
            null,
            [
                'route_key' => 'purchase_assistant',
                'target_type' => 'purchase_assistant_request',
                'target_id' => (string) $request->id,
                'action_label' => 'pay_now',
                'action_route' => $route,
            ],
            null
        );
    }

    public function notifyRejected(PurchaseAssistantRequest $request, User $user): void
    {
        $title = $this->appTitle();
        $body = 'Your Purchase Assistant request could not be fulfilled.';

        Notification::create([
            'user_id' => $user->id,
            'type' => 'orders',
            'title' => $title,
            'subtitle' => $body,
            'read' => false,
            'important' => true,
            'action_label' => 'view_request',
            'action_route' => '/purchase-assistant-requests/'.$request->id,
        ]);

        $this->dispatchService->sendToUser(
            $user,
            $title,
            $body,
            $this->appIconUrl(),
            null,
            [
                'route_key' => 'purchase_assistant',
                'target_type' => 'purchase_assistant_request',
                'target_id' => (string) $request->id,
            ],
            null
        );
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
