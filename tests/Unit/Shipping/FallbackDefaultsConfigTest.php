<?php

namespace Tests\Unit\Shipping;

use App\Models\ShippingSetting;
use App\Services\Shipping\ProductToShippingInputMapper;
use App\Services\Shipping\ShippingPricingConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FallbackDefaultsConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapper_uses_configurable_fallback_weight(): void
    {
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_WEIGHT, '1.2');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_WEIGHT_UNIT, 'kg');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_LENGTH, '20');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_WIDTH, '15');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_HEIGHT, '8');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_DIMENSION_UNIT, 'cm');
        ShippingSetting::clearCache();

        $mapper = app(ProductToShippingInputMapper::class);
        $product = ['name' => 'X', 'price' => 10];
        $result = $mapper->fromNormalizedProduct($product);

        $this->assertTrue($result['estimated']);
        $this->assertContains('weight', $result['missing_fields']);
        $this->assertSame(1.2, (float) $result['input']['weight']);
        $this->assertSame(20.0, (float) $result['input']['length']);
        $this->assertSame(15.0, (float) $result['input']['width']);
        $this->assertSame(8.0, (float) $result['input']['height']);
    }

    public function test_mapper_uses_configurable_fallback_dimension_unit(): void
    {
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_WEIGHT, '0.5');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_WEIGHT_UNIT, 'kg');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_LENGTH, '4');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_WIDTH, '4');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_HEIGHT, '4');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_DIMENSION_UNIT, 'in');
        ShippingSetting::clearCache();

        $mapper = app(ProductToShippingInputMapper::class);
        $product = ['name' => 'X', 'weight' => 1];
        $result = $mapper->fromNormalizedProduct($product);

        $this->assertSame('in', $result['input']['dimension_unit']);
        $this->assertSame(4.0, (float) $result['input']['length']);
    }
}
