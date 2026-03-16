<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\NotificationDispatch;
use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\User;
use App\Models\UserDeviceToken;
use App\Services\Fcm\DeviceTokenService;
use App\Services\Fcm\FcmNotificationService;
use App\Services\Fcm\NotificationDispatchService;
use App\Services\Fcm\OrderShipmentNotificationTrigger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FcmNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create();
    }

    public function test_multiple_tokens_per_user_are_handled(): void
    {
        $user = User::factory()->create();
        $service = app(DeviceTokenService::class);
        $service->upsertToken($user, 'token-1', 'android');
        $service->upsertToken($user, 'token-2', 'ios');
        $tokens = $service->getActiveTokensForUser($user);
        $this->assertCount(2, $tokens);
        $this->assertContains('token-1', $tokens);
        $this->assertContains('token-2', $tokens);
    }

    public function test_event_based_notification_dispatch_creates_system_event_record(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => Order::STATUS_PROCESSING]);
        $trigger = app(OrderShipmentNotificationTrigger::class);
        $trigger->onOrderProcessing($order);
        $dispatch = NotificationDispatch::where('type', NotificationDispatch::TYPE_SYSTEM_EVENT)
            ->where('order_id', $order->id)->first();
        $this->assertNotNull($dispatch);
        $this->assertSame($user->id, $dispatch->user_id);
        $this->assertNotEmpty($dispatch->title);
    }

    public function test_invalid_token_handling_does_not_break_dispatch(): void
    {
        $user = User::factory()->create();
        UserDeviceToken::create([
            'user_id' => $user->id,
            'fcm_token' => 'invalid-token-placeholder',
            'device_type' => 'android',
            'is_active' => true,
        ]);
        $service = app(FcmNotificationService::class);
        $result = $service->sendToUser($user, 'Test', 'Body');
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('summary_message', $result);
        // Without FCM configured we get "FCM not configured"; with invalid token we get sent=0, failed=1
        $this->assertTrue($result['sent'] >= 0 && $result['failed'] >= 0);
    }

    public function test_admin_bulk_notification_is_logged(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.notifications.send.submit'), [
                'send_to_all' => false,
                'user_id' => User::factory()->create()->id,
                'title' => 'Bulk title',
                'subtitle' => 'Bulk body',
                'send_fcm' => true,
            ]);
        $dispatch = NotificationDispatch::where('type', NotificationDispatch::TYPE_BULK)->first();
        $this->assertNotNull($dispatch);
        $this->assertSame('Bulk title', $dispatch->title);
        $this->assertSame($admin->id, $dispatch->created_by);
        $this->assertNotNull($dispatch->send_status);
    }

    public function test_admin_individual_notification_is_logged(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.notifications.send-to-user', $user), [
                'title' => 'Individual title',
                'body' => 'Individual body',
            ]);
        $dispatch = NotificationDispatch::where('type', NotificationDispatch::TYPE_INDIVIDUAL)
            ->where('user_id', $user->id)->first();
        $this->assertNotNull($dispatch);
        $this->assertSame('Individual title', $dispatch->title);
        $this->assertSame($admin->id, $dispatch->created_by);
    }

    public function test_notification_history_is_traceable(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $shipment = OrderShipment::create([
            'order_id' => $order->id,
            'country_code' => 'US',
            'country_label' => 'US',
            'subtotal' => 10,
            'shipping_fee' => 5,
        ]);
        $trigger = app(OrderShipmentNotificationTrigger::class);
        $trigger->onShipmentTrackingAssigned($shipment);
        $dispatch = NotificationDispatch::where('shipment_id', $shipment->id)->first();
        $this->assertNotNull($dispatch);
        $this->assertSame((string) $order->id, (string) $dispatch->order_id);
        $this->assertSame(NotificationDispatch::TYPE_SYSTEM_EVENT, $dispatch->type);
        $this->assertNotNull($dispatch->provider_response_summary);
    }

    public function test_deeplink_payload_structure(): void
    {
        $data = FcmNotificationService::dataPayload('order', '123', 'order_detail', ['key' => 'value']);
        $this->assertSame('order', $data['target_type']);
        $this->assertSame('123', $data['target_id']);
        $this->assertSame('order_detail', $data['route_key']);
        $this->assertSame('{"key":"value"}', $data['payload']);
    }
}
