<?php

namespace Tests\Unit\Shipping;

use App\Models\ShippingSetting;
use App\Services\Shipping\ShippingPricingConfigService;
use App\Services\Shipping\VolumetricWeightCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolumetricWeightCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private VolumetricWeightCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $config = new ShippingPricingConfigService;
        $this->calculator = new VolumetricWeightCalculator($config);
    }

    public function test_volumetric_weight_with_divisor_5000(): void
    {
        // 50*40*30 cm = 60000 cm³ / 5000 = 12 kg
        $vol = $this->calculator->volumetricWeightKg(50, 40, 30);
        $this->assertEqualsWithDelta(12, $vol, 0.001);
    }

    public function test_chargeable_weight_is_max_of_actual_and_volumetric(): void
    {
        $actual = 5.0;
        $volumetric = 12.0;
        $chargeable = $this->calculator->chargeableWeightKg($actual, $volumetric);
        $this->assertEqualsWithDelta(12, $chargeable, 0.001);
    }

    public function test_chargeable_weight_uses_actual_when_higher(): void
    {
        $actual = 15.0;
        $volumetric = 12.0;
        $chargeable = $this->calculator->chargeableWeightKg($actual, $volumetric);
        $this->assertEqualsWithDelta(15, $chargeable, 0.001);
    }

    public function test_compute_returns_volumetric_and_chargeable(): void
    {
        $result = $this->calculator->compute(2, 50, 40, 30);
        $this->assertArrayHasKey('volumetric_kg', $result);
        $this->assertArrayHasKey('chargeable_kg', $result);
        $this->assertEqualsWithDelta(12, $result['volumetric_kg'], 0.001);
        $this->assertEqualsWithDelta(12, $result['chargeable_kg'], 0.001);
    }

    public function test_uses_admin_configurable_divisor_when_set(): void
    {
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_VOLUMETRIC_DIVISOR, '6000');
        ShippingSetting::clearCache();
        $config = new ShippingPricingConfigService;
        $calc = new VolumetricWeightCalculator($config);
        // 60000 / 6000 = 10
        $vol = $calc->volumetricWeightKg(50, 40, 30);
        $this->assertEqualsWithDelta(10, $vol, 0.001);
    }

    public function test_fallback_divisor_when_setting_missing_or_invalid(): void
    {
        // No setting: should use default 5000
        $vol = $this->calculator->volumetricWeightKg(50, 40, 30);
        $this->assertEqualsWithDelta(12, $vol, 0.001);

        ShippingSetting::setValue(ShippingPricingConfigService::KEY_VOLUMETRIC_DIVISOR, '0');
        ShippingSetting::clearCache();
        $config = new ShippingPricingConfigService;
        $calc = new VolumetricWeightCalculator($config);
        $vol2 = $calc->volumetricWeightKg(50, 40, 30);
        $this->assertEqualsWithDelta(12, $vol2, 0.001, 'Invalid divisor should fall back to default');
    }
}
