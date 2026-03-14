<?php

namespace Tests\Feature;

use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\SquareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SquareCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_orders_pay_returns_checkout_url_when_square_mocked(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 49.99,
            'currency' => 'USD',
        ]);

        $fakeCheckoutUrl = 'https://checkout.square.site/fake-link-123';
        $this->mock(SquareService::class, function ($mock) use ($fakeCheckoutUrl) {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->andReturn([
                    'checkout_url' => $fakeCheckoutUrl,
                    'square_payment_id' => null,
                    'square_order_id' => 'sq-order-123',
                ]);
        });

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/orders/{$order->id}/pay");

        $response->assertOk();
        $response->assertJsonPath('data.checkout_url', $fakeCheckoutUrl);
        $response->assertJsonStructure([
            'data' => [
                'payment_id',
                'reference',
                'checkout_url',
                'status',
            ],
        ]);
        $this->assertStringStartsWith('PAY-', $response->json('data.reference'));
        $this->assertEquals(PaymentStatus::Pending->value, $response->json('data.status'));

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => PaymentStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('payment_attempts', [
            'status' => 'success',
        ]);
    }

    public function test_post_orders_pay_returns_404_when_user_does_not_own_order(): void
    {
        $owner = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id]);
        $otherUser = User::factory()->create();

        $this->mock(SquareService::class)->shouldNotReceive('createCheckoutSession');
        Sanctum::actingAs($otherUser);

        $response = $this->postJson("/api/orders/{$order->id}/pay");

        $response->assertNotFound();
    }

    public function test_post_orders_pay_returns_422_when_order_already_paid(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->forOrder($order)->paid()->create();

        $this->mock(SquareService::class)->shouldNotReceive('createCheckoutSession');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Order is already paid.');
    }

    public function test_post_orders_pay_uses_idempotency_key_and_correct_currency(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 99.50,
            'currency' => 'USD',
        ]);

        $capturedPayment = null;
        $this->mock(SquareService::class, function ($mock) use (&$capturedPayment) {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->andReturnUsing(function (Payment $payment) use (&$capturedPayment) {
                    $capturedPayment = $payment;

                    return [
                        'checkout_url' => 'https://checkout.square.site/test',
                        'square_payment_id' => null,
                        'square_order_id' => null,
                    ];
                });
        });

        Sanctum::actingAs($user);
        $this->postJson("/api/orders/{$order->id}/pay");

        $this->assertNotNull($capturedPayment);
        $this->assertEquals($order->id, $capturedPayment->order_id);
        $this->assertEquals('99.50', (string) $capturedPayment->amount);
        $this->assertEquals('USD', $capturedPayment->currency);
        $this->assertNotNull($capturedPayment->idempotency_key);
    }
}
