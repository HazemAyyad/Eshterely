<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\City;
use App\Models\Country;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutWalletBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_review_returns_amount_due_now_with_wallet_applied(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $country = Country::create(['code' => 'AE', 'name' => 'UAE']);
        $city = City::create(['country_id' => $country->id, 'name' => 'Dubai', 'code' => 'DXB']);

        Address::create([
            'user_id' => $user->id,
            'country_id' => $country->id,
            'city_id' => $city->id,
            'address_line' => 'Test Address',
            'is_default' => true,
            'phone' => '+971500000000',
        ]);

        Wallet::create([
            'user_id' => $user->id,
            'available_balance' => 30.00,
            'pending_balance' => 0,
            'promo_balance' => 0,
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'product_url' => 'https://example.com/p/1',
            'name' => 'Item A',
            'unit_price' => 20.00,
            'quantity' => 2,
            'currency' => 'USD',
            'shipping_cost' => 5.00,
            'review_status' => CartItem::REVIEW_STATUS_REVIEWED,
            'needs_review' => false,
            'estimated' => false,
        ]);

        // subtotal=40, app fee=0, payable base=40; shipping=10 is estimate-only (not in total); wallet_applied=30, due=10
        $res = $this->getJson('/api/checkout/review');
        $res->assertOk();
        $res->assertJsonPath('pricing.subtotal', 40);
        $res->assertJsonPath('pricing.app_fee_total', 0);
        $res->assertJsonPath('pricing.shipping_estimate_amount', 10);
        $res->assertJsonPath('pricing.payable_now_total', 40);
        $res->assertJsonPath('pricing.shipping_payable_now', 0);
        $res->assertJsonPath('pricing.total', 40);
        $res->assertJsonPath('pricing.wallet_balance', 30);
        $res->assertJsonPath('pricing.wallet_applied_amount', 30);
        $res->assertJsonPath('pricing.amount_due_now', 10);
        $res->assertJsonPath('pricing.estimated', false);
    }
}

