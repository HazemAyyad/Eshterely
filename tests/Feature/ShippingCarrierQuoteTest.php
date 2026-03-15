<?php

namespace Tests\Feature;

use App\Models\ShippingSetting;
use App\Models\User;
use App\Services\Shipping\ShippingPricingConfigService;
use App\Services\Shipping\ShippingQuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShippingCarrierQuoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedShippingSettings();
    }

    private function seedShippingSettings(): void
    {
        $defaults = [
            ShippingPricingConfigService::KEY_VOLUMETRIC_DIVISOR => '5000',
            ShippingPricingConfigService::KEY_DEFAULT_CURRENCY => 'USD',
            ShippingPricingConfigService::KEY_MIN_SHIPPING_CHARGE => '10',
            ShippingPricingConfigService::KEY_WAREHOUSE_HANDLING_FEE => '3',
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_DHL => '5',
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_UPS => '0',
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_FEDEX => '10',
        ];
        foreach ($defaults as $key => $value) {
            ShippingSetting::setValue($key, $value);
        }
        ShippingSetting::clearCache();
    }

    public function test_quote_preview_accepts_carrier_dhl_ups_fedex_auto(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach (['dhl', 'ups', 'fedex', 'auto'] as $carrier) {
            $response = $this->postJson('/api/shipping/quote-preview', [
                'destination_country' => 'US',
                'carrier' => $carrier,
                'weight' => 1,
                'length' => 10,
                'width' => 10,
                'height' => 10,
            ]);
            $response->assertStatus(200);
            $response->assertJsonPath('success', true);
            $quote = $response->json('quote');
            $this->assertArrayHasKey('carrier', $quote);
            $this->assertArrayHasKey('pricing_mode', $quote);
            $this->assertArrayHasKey('amount', $quote);
            $this->assertArrayHasKey('calculation_breakdown', $quote);
        }
    }

    public function test_auto_carrier_returns_best_option(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/shipping/quote-preview', [
            'destination_country' => 'US',
            'carrier' => 'auto',
            'weight' => 1,
            'length' => 10,
            'width' => 10,
            'height' => 10,
        ]);
        $response->assertStatus(200);
        $quote = $response->json('quote');
        $this->assertNotEmpty($quote['carrier']);
        $this->assertContains($quote['carrier'], ['dhl', 'ups', 'fedex']);
        $carrierResults = $quote['carrier_results'] ?? [];
        $this->assertGreaterThanOrEqual(1, count($carrierResults));
    }

    public function test_warehouse_mode_increases_amount(): void
    {
        $service = app(ShippingQuoteService::class);
        $input = [
            'destination_country' => 'US',
            'carrier' => 'dhl',
            'warehouse_mode' => false,
            'weight' => 1,
            'length' => 10,
            'width' => 10,
            'height' => 10,
        ];

        $resultDirect = $service->quote($input);
        $input['warehouse_mode'] = true;
        $resultWarehouse = $service->quote($input);

        $this->assertGreaterThanOrEqual($resultDirect->finalAmount, $resultWarehouse->finalAmount);
    }

    public function test_multi_package_applies_adjustment(): void
    {
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_MULTI_PACKAGE_PERCENT, '15');
        ShippingSetting::clearCache();

        $service = app(ShippingQuoteService::class);
        $single = $service->quote([
            'destination_country' => 'US',
            'carrier' => 'dhl',
            'weight' => 1,
            'length' => 10,
            'width' => 10,
            'height' => 10,
            'quantity' => 1,
        ]);
        $multi = $service->quote([
            'destination_country' => 'US',
            'carrier' => 'dhl',
            'weight' => 1,
            'length' => 10,
            'width' => 10,
            'height' => 10,
            'quantity' => 3,
        ]);

        $this->assertGreaterThan($single->finalAmount, $multi->finalAmount);
        $this->assertGreaterThan($single->chargeableWeightKg, $multi->chargeableWeightKg);
    }
}
