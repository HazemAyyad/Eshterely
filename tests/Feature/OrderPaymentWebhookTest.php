<?php

namespace Tests\Feature;

use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class OrderPaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('square.webhook_signature_key', 'test-key');
        Config::set('square.webhook_notification_url', 'https://example.com/webhooks/square');
        Config::set('square.webhook_skip_verification', true);
    }

    private function paymentUpdatedPayload(string $squarePaymentId, string $squareOrderId, string $status, array $paymentExtra = []): array
    {
        $payment = array_merge([
            'id' => $squarePaymentId,
            'order_id' => $squareOrderId,
            'status' => $status,
        ], $paymentExtra);

        return [
            'merchant_id' => 'MERCHANT_ID',
            'type' => 'payment.updated',
            'event_id' => 'evt_' . uniqid(),
            'created_at' => now()->toIso8601String(),
            'data' => [
                'type' => 'payment',
                'id' => $squarePaymentId,
                'object' => [
                    'payment' => $payment,
                ],
            ],
        ];
    }

    public function test_webhook_marks_payment_paid_and_updates_order_status(): void
    {
        $order = Order::factory()->pendingPayment()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'placed_at' => null,
        ]);
        $payment = Payment::factory()->forOrder($order)->pending()->create([
            'provider_order_id' => 'sq-order-abc',
        ]);

        $payload = $this->paymentUpdatedPayload('sq-pay-123', 'sq-order-abc', 'COMPLETED');

        $response = $this->postJson(route('webhooks.square'), $payload);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertTrue($payment->isPaid());

        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertNotNull($order->placed_at);
    }

    public function test_duplicate_webhook_does_not_duplicate_order_transition(): void
    {
        $order = Order::factory()->pendingPayment()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'placed_at' => null,
        ]);
        $payment = Payment::factory()->forOrder($order)->pending()->create([
            'provider_order_id' => 'sq-order-dup',
        ]);

        $payload = $this->paymentUpdatedPayload('sq-pay-dup', 'sq-order-dup', 'COMPLETED');

        $this->postJson(route('webhooks.square'), $payload)->assertStatus(200);
        $order->refresh();
        $placedAtFirst = $order->placed_at;
        $this->assertNotNull($placedAtFirst);
        $this->assertSame(Order::STATUS_PAID, $order->status);

        $this->postJson(route('webhooks.square'), $payload)->assertStatus(200);
        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertEquals($placedAtFirst->format('Y-m-d H:i:s'), $order->placed_at->format('Y-m-d H:i:s'));
    }

    public function test_failed_payment_does_not_mark_order_paid(): void
    {
        $order = Order::factory()->pendingPayment()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'placed_at' => null,
        ]);
        $payment = Payment::factory()->forOrder($order)->pending()->create([
            'provider_order_id' => 'sq-order-fail',
        ]);

        $payload = $this->paymentUpdatedPayload('sq-pay-fail', 'sq-order-fail', 'FAILED', [
            'failure_code' => 'DECLINED',
            'failure_message' => 'Card declined',
        ]);

        $response = $this->postJson(route('webhooks.square'), $payload);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);

        $order->refresh();
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertNull($order->placed_at);
    }
}
