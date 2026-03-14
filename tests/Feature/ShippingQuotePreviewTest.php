<?php

namespace Tests\Feature;

use App\Models\ShippingSetting;
use App\Services\Shipping\ShippingPricingConfigService;
use App\Services\Shipping\ShippingQuoteService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShippingQuotePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runShippingMigrationsAndSeedSettings();
    }

    private function runShippingMigrationsAndSeedSettings(): void
    {
        // RefreshDatabase already ran all migrations; ensure default settings exist for consistent quotes
        $defaults = [
            ShippingPricingConfigService::KEY_VOLUMETRIC_DIVISOR => '5000',
            ShippingPricingConfigService::KEY_DEFAULT_CURRENCY => 'USD',
            ShippingPricingConfigService::KEY_MIN_SHIPPING_CHARGE => '0',
            ShippingPricingConfigService::KEY_WAREHOUSE_HANDLING_FEE => '0',
        ];
        foreach ($defaults as $key => $value) {
            if (! ShippingSetting::query()->where('key', $key)->exists()) {
                ShippingSetting::setValue($key, $value);
            }
        }
    }

    public function test_quote_preview_requires_authentication(): void
    {
        $response = $this->postJson('/api/shipping/quote-preview', [
            'destination_country' => 'US',
            'weight' => 1,
            'length' => 10,
            'width' => 10,
            'height' => 10,
        ]);
        $response->assertStatus(401);
    }

    public function test_quote_preview_validates_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $response = $this->postJson('/api/shipping/quote-preview', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['destination_country', 'weight', 'length', 'width', 'height']);
    }

    public function test_quote_preview_returns_calculation_structure(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $response = $this->postJson('/api/shipping/quote-preview', [
            'destination_country' => 'US',
            'weight' => 2,
            'weight_unit' => 'kg',
            'length' => 50,
            'width' => 40,
            'height' => 30,
            'dimension_unit' => 'cm',
            'quantity' => 1,
            'warehouse_mode' => false,
        ]);
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'quote' => [
                'actual_weight_kg',
                'volumetric_weight_kg',
                'chargeable_weight_kg',
                'currency',
                'final_amount',
                'calculation_notes',
                'applied_config_snapshot',
            ],
        ]);
        $quote = $response->json('quote');
        $this->assertEqualsWithDelta(2, $quote['actual_weight_kg'], 0.001);
        // 50*40*30/5000 = 12 kg volumetric
        $this->assertEqualsWithDelta(12, $quote['volumetric_weight_kg'], 0.001);
        $this->assertEqualsWithDelta(12, $quote['chargeable_weight_kg'], 0.001);
        $this->assertEquals('USD', $quote['currency']);
    }

    public function test_quote_service_uses_admin_config(): void
    {
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_VOLUMETRIC_DIVISOR, '6000');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_DEFAULT_CURRENCY, 'SAR');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_MIN_SHIPPING_CHARGE, '10');
        ShippingSetting::clearCache();

        $service = app(ShippingQuoteService::class);
        $result = $service->quote([
            'destination_country' => 'AE',
            'weight' => 1,
            'length' => 60,
            'width' => 50,
            'height' => 40,
        ]);
        $this->assertEquals('SAR', $result->currency);
        $this->assertGreaterThanOrEqual(10, $result->finalAmount);
        // 60*50*40/6000 = 20 kg volumetric
        $this->assertEqualsWithDelta(20, $result->volumetricWeightKg, 0.001);
    }
}
