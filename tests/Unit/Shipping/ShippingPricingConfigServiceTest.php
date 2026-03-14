<?php

namespace Tests\Unit\Shipping;

use App\Models\ShippingSetting;
use App\Services\Shipping\ShippingPricingConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingPricingConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShippingPricingConfigService $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new ShippingPricingConfigService;
    }

    public function test_volumetric_divisor_fallback_when_empty(): void
    {
        $this->assertEquals(5000.0, $this->config->volumetricDivisor());
    }

    public function test_volumetric_divisor_reads_from_settings(): void
    {
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_VOLUMETRIC_DIVISOR, '6000');
        ShippingSetting::clearCache();
        $config = new ShippingPricingConfigService;
        $this->assertEquals(6000.0, $config->volumetricDivisor());
    }

    public function test_default_currency_fallback(): void
    {
        $this->assertEquals('USD', $this->config->defaultCurrency());
    }

    public function test_default_currency_reads_from_settings(): void
    {
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_DEFAULT_CURRENCY, 'SAR');
        ShippingSetting::clearCache();
        $config = new ShippingPricingConfigService;
        $this->assertEquals('SAR', $config->defaultCurrency());
    }

    public function test_min_shipping_charge_fallback(): void
    {
        $this->assertEquals(0.0, $this->config->minShippingCharge());
    }

    public function test_warehouse_handling_fee_reads_from_settings(): void
    {
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_WAREHOUSE_HANDLING_FEE, '5.5');
        ShippingSetting::clearCache();
        $config = new ShippingPricingConfigService;
        $this->assertEquals(5.5, $config->warehouseHandlingFee());
    }

    public function test_snapshot_for_quote_returns_safe_keys(): void
    {
        $snap = $this->config->snapshotForQuote();
        $this->assertArrayHasKey('volumetric_divisor', $snap);
        $this->assertArrayHasKey('default_currency', $snap);
        $this->assertArrayHasKey('min_shipping_charge', $snap);
        $this->assertArrayHasKey('warehouse_handling_fee', $snap);
        $this->assertArrayHasKey('rounding_strategy', $snap);
    }

    public function test_editable_keys_contains_expected_keys(): void
    {
        $keys = ShippingPricingConfigService::editableKeys();
        $this->assertContains(ShippingPricingConfigService::KEY_VOLUMETRIC_DIVISOR, $keys);
        $this->assertContains(ShippingPricingConfigService::KEY_DEFAULT_CURRENCY, $keys);
        $this->assertContains(ShippingPricingConfigService::KEY_WAREHOUSE_HANDLING_FEE, $keys);
    }
}
