<?php

namespace Tests\Feature;

use App\Enums\Payment\PaymentEventSource;
use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SquareWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('square.webhook_signature_key', 'test-key');
        Config::set('square.webhook_notification_url', 'https://example.com/webhooks/square');
    }

    /** @return array<string, mixed> */
    protected function paymentUpdatedPayload(string $squarePaymentId, string $squareOrderId, string $status): array
    {
        return [
            'merchant_id' => 'MERCHANT_ID',
            'type' => 'payment.updated',
            'event_id' => 'evt_' . uniqid(),
            'created_at' => now()->toIso8601String(),
            'data' => [
                'type' => 'payment',
                'id' => $squarePaymentId,
                'object' => [
                    'id' => $squarePaymentId,
                    'order_id' => $squareOrderId,
                    'status' => $status,
                ],
            ],
        ];
    }

    public function test_valid_payment_updated_webhook_marks_payment_as_paid(): void
    {
        Config::set('square.webhook_skip_verification', true);

        $order = Order::factory()->create();
        $payment = Payment::factory()->forOrder($order)->pending()->create([
            'provider_order_id' => 'sq-order-abc',
        ]);

        $payload = $this->paymentUpdatedPayload('sq-pay-123', 'sq-order-abc', 'COMPLETED');

        $response = $this->postJson(route('webhooks.square'), $payload);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertTrue($payment->isPaid());
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('sq-pay-123', $payment->provider_payment_id);

        $events = $payment->events()->where('source', PaymentEventSource::Webhook)->get();
        $this->assertGreaterThanOrEqual(1, $events->count());
        $eventTypes = $events->pluck('event_type')->toArray();
        $this->assertContains('payment.updated', $eventTypes);
        $this->assertContains('payment.paid', $eventTypes);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        Config::set('square.webhook_skip_verification', false);

        $payload = $this->paymentUpdatedPayload('sq-pay-1', 'sq-order-1', 'COMPLETED');

        $response = $this->postJson(route('webhooks.square'), $payload, [
            'X-Square-HmacSha256-Signature' => 'invalid-signature',
        ]);

        $response->assertStatus(403);
    }

    public function test_duplicate_webhook_does_not_duplicate_paid_transition(): void
    {
        Config::set('square.webhook_skip_verification', true);

        $order = Order::factory()->create();
        $payment = Payment::factory()->forOrder($order)->pending()->create([
            'provider_order_id' => 'sq-order-dup',
        ]);

        $payload = $this->paymentUpdatedPayload('sq-pay-dup', 'sq-order-dup', 'COMPLETED');

        $this->postJson(route('webhooks.square'), $payload)->assertStatus(200);
        $payment->refresh();
        $paidAtFirst = $payment->paid_at;
        $this->assertNotNull($paidAtFirst);

        $this->postJson(route('webhooks.square'), $payload)->assertStatus(200);
        $payment->refresh();
        $this->assertTrue($payment->isPaid());
        $this->assertEquals($paidAtFirst->format('Y-m-d H:i:s'), $payment->paid_at->format('Y-m-d H:i:s'));

        $paidEvents = $payment->events()->where('event_type', 'payment.paid')->count();
        $this->assertSame(1, $paidEvents);
    }

    public function test_unknown_payment_reference_returns_200_without_crashing(): void
    {
        Config::set('square.webhook_skip_verification', true);

        $payload = $this->paymentUpdatedPayload('unknown-pay', 'unknown-order', 'COMPLETED');

        $response = $this->postJson(route('webhooks.square'), $payload);

        $response->assertStatus(200);
        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_failed_payment_webhook_marks_payment_failed(): void
    {
        Config::set('square.webhook_skip_verification', true);

        $order = Order::factory()->create();
        $payment = Payment::factory()->forOrder($order)->pending()->create([
            'provider_order_id' => 'sq-order-fail',
        ]);

        $payload = [
            'merchant_id' => 'MERCHANT_ID',
            'type' => 'payment.updated',
            'event_id' => 'evt_fail',
            'created_at' => now()->toIso8601String(),
            'data' => [
                'type' => 'payment',
                'id' => 'sq-pay-fail',
                'object' => [
                    'id' => 'sq-pay-fail',
                    'order_id' => 'sq-order-fail',
                    'status' => 'FAILED',
                    'failure_code' => 'DECLINED',
                    'failure_message' => 'Card declined',
                ],
            ],
        ];

        $response = $this->postJson(route('webhooks.square'), $payload);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('DECLINED', $payment->failure_code);
        $this->assertSame('Card declined', $payment->failure_message);
    }

    public function test_payment_resolved_by_provider_payment_id(): void
    {
        Config::set('square.webhook_skip_verification', true);

        $order = Order::factory()->create();
        $payment = Payment::factory()->forOrder($order)->pending()->create([
            'provider_payment_id' => 'sq-pay-by-id',
            'provider_order_id' => null,
        ]);

        $payload = $this->paymentUpdatedPayload('sq-pay-by-id', 'sq-order-any', 'COMPLETED');

        $response = $this->postJson(route('webhooks.square'), $payload);

        $response->assertStatus(200);
        $payment->refresh();
        $this->assertTrue($payment->isPaid());
    }

    public function test_empty_body_returns_400(): void
    {
        Config::set('square.webhook_skip_verification', true);

        $response = $this->withBody('', 'application/json')
            ->post(route('webhooks.square'));

        $response->assertStatus(400);
    }
}
