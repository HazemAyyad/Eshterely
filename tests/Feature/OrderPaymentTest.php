<?php

namespace Tests\Feature;

use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentEligibilityService;
use App\Services\Payments\SquareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_eligible_order_can_start_payment(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->pendingPayment()->create([
            'user_id' => $user->id,
            'order_total_snapshot' => 75.00,
            'total_amount' => 75.00,
            'currency' => 'USD',
        ]);

        $fakeUrl = 'https://checkout.square.site/start-123';
        $this->mock(SquareService::class, function ($mock) use ($fakeUrl) {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->andReturn([
                    'checkout_url' => $fakeUrl,
                    'square_payment_id' => null,
                    'square_order_id' => 'sq-ord-1',
                ]);
        });

        Sanctum::actingAs($user);
        $response = $this->postJson("/api/orders/{$order->id}/start-payment");

        $response->assertStatus(200);
        $response->assertJsonPath('data.checkout_url', $fakeUrl);
        $response->assertJsonPath('data.provider', 'square');
        $response->assertJsonPath('data.status', PaymentStatus::Pending->value);
        $response->assertJsonPath('data.order_id', (string) $order->id);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => PaymentStatus::Pending->value,
            'amount' => 75.00,
        ]);
    }

    public function test_ineligible_order_cannot_start_payment_already_paid(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->pendingPayment()->create(['user_id' => $user->id]);
        Payment::factory()->forOrder($order)->paid()->create();

        $this->mock(SquareService::class)->shouldNotReceive('createCheckoutSession');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/orders/{$order->id}/start-payment");

        $response->assertStatus(422);
        $response->assertJsonPath('error_key', 'already_paid');
    }

    public function test_ineligible_order_cannot_start_payment_wrong_status(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PAID,
        ]);

        $this->mock(SquareService::class)->shouldNotReceive('createCheckoutSession');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/orders/{$order->id}/start-payment");

        $response->assertStatus(422);
        $response->assertJsonPath('error_key', 'invalid_order_status');
    }

    public function test_payment_record_linked_to_order_and_uses_snapshot_amount(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->pendingPayment()->create([
            'user_id' => $user->id,
            'order_total_snapshot' => 123.45,
            'total_amount' => 123.45,
            'currency' => 'USD',
        ]);

        $this->mock(SquareService::class, function ($mock) {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->andReturn([
                    'checkout_url' => 'https://square.site/pay',
                    'square_payment_id' => null,
                    'square_order_id' => null,
                ]);
        });

        Sanctum::actingAs($user);
        $this->postJson("/api/orders/{$order->id}/start-payment");

        $payment = Payment::where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals($order->id, $payment->order_id);
        $this->assertEquals($user->id, $payment->user_id);
        $this->assertEquals(123.45, (float) $payment->amount);
    }

    public function test_start_payment_enforces_ownership(): void
    {
        $owner = User::factory()->create();
        $order = Order::factory()->pendingPayment()->create(['user_id' => $owner->id]);
        $other = User::factory()->create();

        $this->mock(SquareService::class)->shouldNotReceive('createCheckoutSession');
        Sanctum::actingAs($other);

        $response = $this->postJson("/api/orders/{$order->id}/start-payment");

        $response->assertStatus(404);
    }

    public function test_eligibility_blocks_cancelled_order(): void
    {
        $order = Order::factory()->create([
            'status' => Order::STATUS_CANCELLED,
        ]);

        $service = app(PaymentEligibilityService::class);
        $result = $service->checkOrderEligibility($order);

        $this->assertFalse($result['eligible']);
        $this->assertSame('order_cancelled', $result['error_key']);
    }
}
