<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Country;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Payments\SquareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutPromoTest extends TestCase
{
    use RefreshDatabase;

    private function seedDefaultCheckoutState(User $user, float $walletBalance = 0.0): void
    {
        $country = Country::create([
            'code' => 'US',
            'name' => 'United States',
            'flag_emoji' => '🇺🇸',
        ]);

        Address::create([
            'user_id' => $user->id,
            'country_id' => $country->id,
            'address_line' => '123 Main St, Test City',
            'is_default' => true,
        ]);

        Wallet::create([
            'user_id' => $user->id,
            'available_balance' => $walletBalance,
            'pending_balance' => 0,
            'promo_balance' => 0,
        ]);
    }

    private function seedReviewedCartItem(User $user, float $unitPrice = 100.0, int $quantity = 1, float $shippingCost = 20.0): CartItem
    {
        return CartItem::create([
            'user_id' => $user->id,
            'product_url' => 'https://example.com/product/1',
            'name' => 'Test Product',
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'currency' => 'USD',
            'image_url' => null,
            'store_key' => 'amazon',
            'store_name' => 'Amazon',
            'product_id' => 'SKU-1',
            'country' => 'US',
            'source' => CartItem::SOURCE_PASTE_LINK,
            'review_status' => CartItem::REVIEW_STATUS_REVIEWED,
            'shipping_cost' => $shippingCost,
            'pricing_snapshot' => ['subtotal' => $unitPrice * $quantity],
            'shipping_snapshot' => ['method' => 'air', 'eta' => '5-7 days'],
            'estimated' => false,
            'missing_fields' => [],
            'carrier' => 'dhl',
            'pricing_mode' => 'default',
            'needs_review' => false,
        ]);
    }

    private function seedPromoCode(array $overrides = []): PromoCode
    {
        return PromoCode::create(array_merge([
            'code' => 'SAVE10',
            'description' => 'Ten percent off',
            'is_active' => true,
            'discount_type' => 'percent',
            'discount_value' => 10,
            'min_order_amount' => null,
            'max_discount_amount' => null,
            'max_usage_total' => null,
            'max_usage_per_user' => null,
            'starts_at' => null,
            'ends_at' => null,
        ], $overrides));
    }

    public function test_checkout_applies_promo_before_wallet_and_persists_breakdown(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->seedDefaultCheckoutState($user, 20);
        $this->seedReviewedCartItem($user, 100, 1, 20);
        $this->seedPromoCode();

        $review = $this->getJson('/api/checkout/review?promo_code=SAVE10');
        $review->assertOk();
        $review->assertJsonPath('promo_valid', true);
        $review->assertJsonPath('promo_code', 'SAVE10');
        // Base = product 100 + app fee 0 = 100; promo 10% = 10; after promo 90; wallet 20 → due 70
        $this->assertEqualsWithDelta(10.0, (float) $review->json('promo_discount_amount'), 0.01);
        $this->assertEqualsWithDelta(70.0, (float) $review->json('amount_due_now'), 0.01);

        $confirm = $this->postJson('/api/checkout/confirm', [
            'use_wallet_balance' => true,
            'promo_code' => 'SAVE10',
        ]);

        $confirm->assertStatus(201);
        $this->assertEqualsWithDelta(90.0, (float) $confirm->json('pricing.total'), 0.01);
        $this->assertEqualsWithDelta(10.0, (float) $confirm->json('pricing.discounts'), 0.01);
        $this->assertEqualsWithDelta(20.0, (float) $confirm->json('pricing.wallet_applied_amount'), 0.01);
        $this->assertEqualsWithDelta(70.0, (float) $confirm->json('pricing.amount_due_now'), 0.01);

        $order = Order::findOrFail($confirm->json('order_id'));
        $this->assertSame('SAVE10', $order->promo_code);
        $this->assertEqualsWithDelta(10.0, (float) $order->promo_discount_amount, 0.01);
        $this->assertEqualsWithDelta(20.0, (float) $order->wallet_applied_amount, 0.01);
        $this->assertEqualsWithDelta(70.0, (float) $order->amount_due_now, 0.01);

        $this->assertDatabaseHas('promo_redemptions', [
            'order_id' => $order->id,
            'promo_code_id' => $order->promo_code_id,
            'code_snapshot' => 'SAVE10',
            'status' => 'applied',
        ]);

        $priceLines = DB::table('order_price_lines')->where('order_id', $order->id)->pluck('label')->all();
        $this->assertContains('Promo discount', $priceLines);
        $this->assertContains('Wallet applied', $priceLines);
    }

    public function test_promo_single_use_limit_blocks_second_checkout(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->seedDefaultCheckoutState($user, 0);
        $this->seedReviewedCartItem($user, 100, 1, 20);
        $this->seedPromoCode([
            'max_usage_per_user' => 1,
            'max_usage_total' => 1,
        ]);

        $this->postJson('/api/checkout/confirm', [
            'use_wallet_balance' => true,
            'promo_code' => 'SAVE10',
        ])->assertStatus(201);

        $this->seedReviewedCartItem($user, 100, 1, 20);
        $second = $this->postJson('/api/checkout/confirm', [
            'use_wallet_balance' => true,
            'promo_code' => 'SAVE10',
        ]);

        $second->assertStatus(422);
        $second->assertJsonPath('error_key', 'usage_limit');
    }

    public function test_payment_uses_amount_due_now_after_wallet_and_promo(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->seedDefaultCheckoutState($user, 20);
        $this->seedReviewedCartItem($user, 100, 1, 20);
        $this->seedPromoCode();

        $confirm = $this->postJson('/api/checkout/confirm', [
            'use_wallet_balance' => true,
            'promo_code' => 'SAVE10',
        ]);
        $confirm->assertStatus(201);
        $order = Order::findOrFail($confirm->json('order_id'));
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertEqualsWithDelta(70.0, (float) $order->amount_due_now, 0.01);

        $checkoutUrl = 'https://square.example/checkout';
        $this->mock(SquareService::class, function ($mock) use ($checkoutUrl) {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->andReturn([
                    'checkout_url' => $checkoutUrl,
                    'square_payment_id' => null,
                    'square_order_id' => 'sq-order-1',
                ]);
        });

        $payment = $this->postJson("/api/orders/{$order->id}/pay");
        $payment->assertOk();
        $payment->assertJsonPath('data.checkout_url', $checkoutUrl);

        $createdPayment = Payment::where('order_id', $order->id)->latest('id')->first();
        $this->assertNotNull($createdPayment);
        $this->assertEqualsWithDelta(70.0, (float) $createdPayment->amount, 0.01);
    }
}
