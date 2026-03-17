<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTopUpPayment;
use App\Services\Payments\SquareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletTopUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_top_up_creates_payment_and_returns_checkout_url(): void
    {
        $user = User::factory()->create();
        Wallet::create([
            'user_id' => $user->id,
            'available_balance' => 0,
            'pending_balance' => 0,
            'promo_balance' => 0,
        ]);

        $fakeCheckoutUrl = 'https://checkout.square.site/fake-topup';
        $this->mock(SquareService::class, function ($mock) use ($fakeCheckoutUrl) {
            $mock->shouldReceive('createWalletTopUpCheckoutSession')
                ->once()
                ->andReturn([
                    'checkout_url' => $fakeCheckoutUrl,
                    'square_payment_id' => null,
                    'square_order_id' => 'sq-topup-order-1',
                ]);
        });

        Sanctum::actingAs($user);

        $res = $this->postJson('/api/wallet/top-up', ['amount' => 25]);
        $res->assertStatus(201);
        $res->assertJsonPath('top_up.checkout_url', $fakeCheckoutUrl);
        $res->assertJsonPath('top_up.amount', 25);

        $this->assertDatabaseHas('wallet_top_up_payments', [
            'user_id' => $user->id,
            'amount' => '25.00',
            'provider_order_id' => 'sq-topup-order-1',
        ]);

        $wallet = Wallet::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('0.00', (string) $wallet->available_balance);
    }

    public function test_square_webhook_completed_marks_top_up_paid_and_credits_wallet_once(): void
    {
        config()->set('square.webhook_skip_verification', true);

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
            'provider' => 'square',
            'currency' => 'USD',
            'amount' => 10.00,
            'status' => 'pending',
            'reference' => 'PAY-TOPUP-REF',
            'provider_order_id' => 'sq-topup-order-2',
        ]);

        $payload = [
            'merchant_id' => 'MERCHANT_ID',
            'type' => 'payment.updated',
            'event_id' => 'evt_' . uniqid(),
            'created_at' => now()->toIso8601String(),
            'data' => [
                'type' => 'payment',
                'id' => 'sq-pay-topup-1',
                'object' => [
                    'payment' => [
                        'id' => 'sq-pay-topup-1',
                        'order_id' => 'sq-topup-order-2',
                        'status' => 'COMPLETED',
                        'reference_id' => 'PAY-TOPUP-REF',
                    ],
                ],
            ],
        ];

        $this->postJson(route('webhooks.square'), $payload)->assertStatus(200);

        $topUp->refresh();
        $this->assertSame('paid', $topUp->status);
        $this->assertNotNull($topUp->paid_at);
        $this->assertSame('sq-pay-topup-1', $topUp->provider_payment_id);

        $wallet->refresh();
        $this->assertSame('10.00', (string) $wallet->available_balance);

        // duplicate webhook should not double-credit
        $this->postJson(route('webhooks.square'), $payload)->assertStatus(200);
        $wallet->refresh();
        $this->assertSame('10.00', (string) $wallet->available_balance);
    }
}

