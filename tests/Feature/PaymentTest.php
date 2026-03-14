<?php

namespace Tests\Feature;

use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = app(PaymentService::class);
    }

    public function test_creates_pending_payment_for_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'total_amount' => 99.99, 'currency' => 'USD']);

        $payment = $this->paymentService->createPendingPaymentForOrder($order, ['provider' => 'square']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => PaymentStatus::Pending->value,
            'amount' => 99.99,
            'currency' => 'USD',
            'provider' => 'square',
        ]);
        $this->assertStringStartsWith('PAY-', $payment->reference);
        $this->assertNull($payment->paid_at);
        $this->assertCount(1, $payment->events);
    }

    public function test_marking_payment_paid_sets_paid_at_and_status(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->forOrder($order)->pending()->create();

        $this->paymentService->markPaid($payment, ['provider_payment_id' => 'sq_123']);

        $payment->refresh();
        $this->assertTrue($payment->isPaid());
        $this->assertEquals(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_marking_payment_paid_is_idempotent(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->forOrder($order)->paid()->create();
        $paidAt = $payment->paid_at;

        $this->paymentService->markPaid($payment);

        $payment->refresh();
        $this->assertEquals($paidAt->format('Y-m-d H:i:s'), $payment->paid_at->format('Y-m-d H:i:s'));
    }

    public function test_marking_payment_failed(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->forOrder($order)->pending()->create();

        $this->paymentService->markFailed($payment, 'DECLINED', 'Card declined');

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Failed, $payment->status);
        $this->assertEquals('DECLINED', $payment->failure_code);
        $this->assertEquals('Card declined', $payment->failure_message);
    }

    public function test_user_can_retrieve_own_payment(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $payment = Payment::factory()->forOrder($order)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/payments/{$payment->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $payment->id);
        $response->assertJsonPath('data.reference', $payment->reference);
        $response->assertJsonPath('data.status', PaymentStatus::Pending->value);
    }

    public function test_user_cannot_retrieve_another_users_payment(): void
    {
        $otherUser = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $otherUser->id]);
        $payment = Payment::factory()->forOrder($order)->create();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/payments/{$payment->id}");

        $response->assertForbidden();
    }

    public function test_order_payments_list_returns_only_for_owner(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->forOrder($order)->count(2)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/orders/{$order->id}/payments");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_order_payments_list_blocks_another_users_order(): void
    {
        $otherUser = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $otherUser->id]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/orders/{$order->id}/payments");

        $response->assertNotFound();
    }
}
