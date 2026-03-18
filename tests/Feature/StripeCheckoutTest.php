<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTopUpPayment;
use App\Services\Payments\PaymentGatewayManager;
use App\Contracts\Payments\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Mockery;

class StripeCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function enableStripeDefault(): void
    {
        DB::table('payment_gateway_settings')->insert([
            'id' => 1,
            'default_gateway' => 'stripe',
            'square_enabled' => false,
            'square_environment' => 'sandbox',
            'stripe_enabled' => true,
            'stripe_environment' => 'test',
            'stripe_currency_default' => 'USD',
            'stripe_publishable_key' => 'pk_test_dummy',
            'stripe_secret_key' => 'sk_test_dummy',
            'stripe_webhook_secret' => 'whsec_dummy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function enableSquareDefault(): void
    {
        DB::table('payment_gateway_settings')->insert([
            'id' => 1,
            'default_gateway' => 'square',
            'square_enabled' => true,
            'square_environment' => 'sandbox',
            'stripe_enabled' => false,
            'stripe_environment' => 'test',
            'stripe_currency_default' => 'USD',
            'stripe_publishable_key' => null,
            'stripe_secret_key' => null,
            'stripe_webhook_secret' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_post_orders_pay_returns_checkout_url_when_stripe_is_mocked(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->pendingPayment()->create([
            'user_id' => $user->id,
            'total_amount' => 49.99,
            'currency' => 'USD',
        ]);

        $fakeCheckoutUrl = 'https://checkout.stripe.test/fake-link-123';

        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('gatewayCode')
            ->andReturn('stripe');
        $gatewayMock->shouldReceive('createOrderCheckoutSession')
            ->once()
            ->andReturn([
                'checkout_url' => $fakeCheckoutUrl,
                'provider_payment_id' => 'cs_test_123',
                'provider_order_id' => (string) $order->id,
                'provider' => 'stripe',
            ]);

        $this->mock(PaymentGatewayManager::class, function ($mock) use ($gatewayMock) {
            $mock->shouldReceive('resolveDefault')
                ->andReturn($gatewayMock);
            $mock->shouldReceive('resolve')
                ->with('stripe')
                ->andReturn($gatewayMock);
        });

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/orders/{$order->id}/pay");

        $response->assertOk();
        $response->assertJsonPath('data.checkout_url', $fakeCheckoutUrl);
        $response->assertJsonPath('data.provider', 'stripe');
        $response->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'provider' => 'stripe',
            'status' => 'pending',
        ]);
    }

    public function test_order_pay_with_explicit_gateway_rejects_disabled_gateway(): void
    {
        $this->enableSquareDefault();

        $user = User::factory()->create();
        $order = Order::factory()->pendingPayment()->create([
            'user_id' => $user->id,
            'total_amount' => 20.00,
            'currency' => 'USD',
        ]);

        $this->mock(StripePaymentGateway::class)->shouldNotReceive('createOrderCheckoutSession');

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/orders/{$order->id}/pay", [
            'gateway' => 'stripe',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_key', 'gateway_unavailable');
    }

    public function test_stripe_webhook_completed_marks_payment_as_paid(): void
    {
        config()->set('stripe.webhook_skip_verification', true);
        $this->enableStripeDefault();

        $order = Order::factory()->pendingPayment()->create();
        $payment = Payment::factory()->forOrder($order)->pending()->create([
            'provider' => 'stripe',
            'reference' => 'PAY-STRIPE-REF-1',
            'provider_payment_id' => null,
            'provider_order_id' => null,
        ]);

        $sessionId = 'cs_test_pay_1';
        $payload = [
            'id' => 'evt_' . uniqid(),
            'type' => 'checkout.session.completed',
            'created' => now()->timestamp,
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'payment_status' => 'paid',
                    'metadata' => [
                        'payment_reference' => $payment->reference,
                        'order_id' => (string) $order->id,
                    ],
                ],
            ],
        ];

        $this->postJson(route('api.webhooks.stripe'), $payload)->assertStatus(200);

        $payment->refresh();
        $order->refresh();

        $this->assertSame('paid', $payment->status->value);
        $this->assertSame($sessionId, $payment->provider_payment_id);
        $this->assertSame(Order::STATUS_PAID, $order->status);
    }

    public function test_stripe_webhook_completed_marks_top_up_paid_and_credits_wallet_once(): void
    {
        config()->set('stripe.webhook_skip_verification', true);
        $this->enableStripeDefault();

        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'available_balance' => 0,
            'pending_balance' => 0,
            'promo_balance' => 0,
        ]);

        $topUp = WalletTopUpPayment::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'provider' => 'stripe',
            'currency' => 'USD',
            'amount' => 10.00,
            'status' => 'pending',
            'reference' => 'PAY-TOPUP-STRIPE-REF-1',
            'provider_payment_id' => null,
            'provider_order_id' => null,
            'metadata' => [],
        ]);

        $sessionId = 'cs_test_topup_1';
        $payload = [
            'id' => 'evt_' . uniqid(),
            'type' => 'checkout.session.completed',
            'created' => now()->timestamp,
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'payment_status' => 'paid',
                    'metadata' => [
                        'wallet_top_up_reference' => $topUp->reference,
                        'wallet_top_up_id' => (string) $topUp->id,
                    ],
                ],
            ],
        ];

        $this->postJson(route('api.webhooks.stripe'), $payload)->assertStatus(200);

        $topUp->refresh();
        $wallet->refresh();

        $this->assertSame('paid', $topUp->status);
        $this->assertSame($sessionId, $topUp->provider_payment_id);
        $this->assertSame('10.00', (string) $wallet->available_balance);

        // duplicate webhook should not double-credit
        $this->postJson(route('api.webhooks.stripe'), $payload)->assertStatus(200);
        $wallet->refresh();
        $this->assertSame('10.00', (string) $wallet->available_balance);
    }

    public function test_wallet_top_up_launches_stripe_checkout_when_stripe_gateway_is_default(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'available_balance' => 0,
            'pending_balance' => 0,
            'promo_balance' => 0,
        ]);

        $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
        $gatewayMock->shouldReceive('gatewayCode')->andReturn('stripe');
        $gatewayMock->shouldReceive('createWalletTopUpCheckoutSession')
            ->once()
            ->andReturn([
                'checkout_url' => 'https://checkout.stripe.test/fake-topup',
                'provider_payment_id' => 'cs_topup_1',
                'provider_order_id' => 'stripe-topup-order-1',
                'provider' => 'stripe',
            ]);

        $this->mock(PaymentGatewayManager::class, function ($mock) use ($gatewayMock) {
            $mock->shouldReceive('resolveDefault')->andReturn($gatewayMock);
        });

        Sanctum::actingAs($user);

        $res = $this->postJson('/api/wallet/top-up', ['amount' => 25]);
        $res->assertStatus(201);
        $res->assertJsonPath('top_up.checkout_url', 'https://checkout.stripe.test/fake-topup');

        $this->assertDatabaseHas('wallet_top_up_payments', [
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'provider' => 'stripe',
            'provider_order_id' => 'stripe-topup-order-1',
        ]);

        // Wallet is credited only after webhook confirmation.
        $wallet->refresh();
        $this->assertSame('0.00', (string) $wallet->available_balance);
    }

    public function test_payment_gateway_manager_resolves_default_from_settings(): void
    {
        DB::table('payment_gateway_settings')->insert([
            'id' => 1,
            'default_gateway' => 'stripe',
            'square_enabled' => true,
            'stripe_enabled' => true,
            'stripe_environment' => 'test',
            'stripe_currency_default' => 'USD',
            'stripe_secret_key' => '',
            'stripe_webhook_secret' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager = app(PaymentGatewayManager::class);
        $this->assertSame('stripe', $manager->getDefaultGatewayCode());
        $this->assertSame('stripe', $manager->resolveDefault()->gatewayCode());

        // Disabled gateway can't be resolved.
        DB::table('payment_gateway_settings')->update([
            'stripe_enabled' => false,
            'default_gateway' => 'stripe',
        ]);
        $this->assertSame('square', $manager->getDefaultGatewayCode());
        $this->expectException(\InvalidArgumentException::class);
        $manager->resolve('stripe');
    }
}

