<?php

namespace Tests\Unit\Shipping;

use App\Models\ShippingSetting;
use App\Services\Shipping\FinalProductPricingService;
use App\Services\Shipping\ShippingPricingConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalProductPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private FinalProductPricingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPricingSettings();
        $this->service = app(FinalProductPricingService::class);
    }

    private function seedPricingSettings(): void
    {
        $defaults = [
            ShippingPricingConfigService::KEY_DEFAULT_CURRENCY => 'USD',
            ShippingPricingConfigService::KEY_SERVICE_FEE => '0',
            ShippingPricingConfigService::KEY_PLATFORM_MARKUP_PERCENT => '0',
            ShippingPricingConfigService::KEY_MINIMUM_ORDER_FEE => '0',
            ShippingPricingConfigService::KEY_MINIMUM_ORDER_THRESHOLD => '0',
        ];
        foreach ($defaults as $key => $value) {
            ShippingSetting::setValue($key, $value);
        }
    }

    public function test_product_plus_shipping_plus_markup_breakdown(): void
    {
        $product = ['price' => 50.00, 'currency' => 'USD'];
        $shippingQuote = [
            'amount' => 15.00,
            'currency' => 'USD',
            'carrier' => 'dhl',
            'pricing_mode' => 'carrier',
            'estimated' => false,
            'notes' => [],
        ];
        $result = $this->service->build($product, $shippingQuote, 1);

        $this->assertNotNull($result);
        $arr = $result->toArray();
        $this->assertSame(50.0, $arr['product_price']);
        $this->assertSame('USD', $arr['product_currency']);
        $this->assertSame(15.0, $arr['shipping_amount']);
        $this->assertSame(65.0, $arr['subtotal']);
        $this->assertSame(65.0, $arr['final_total']);
        $this->assertSame('dhl', $arr['carrier']);
        $this->assertFalse($arr['estimated']);
    }

    public function test_service_fee_application(): void
    {
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_SERVICE_FEE, '5');
        ShippingSetting::clearCache();
        $config = new ShippingPricingConfigService;
        $this->assertSame(5.0, $config->serviceFee());

        $product = ['price' => 10.00, 'currency' => 'USD'];
        $shippingQuote = ['amount' => 5.00, 'currency' => 'USD', 'estimated' => false, 'notes' => []];
        $result = $this->service->build($product, $shippingQuote, 1);

        $this->assertNotNull($result);
        $arr = $result->toArray();
        $this->assertSame(5.0, $arr['service_fee']);
        $this->assertSame(15.0, $arr['subtotal']);
        $this->assertSame(20.0, $arr['final_total']);
    }

    public function test_platform_markup_application(): void
    {
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_PLATFORM_MARKUP_PERCENT, '10');
        ShippingSetting::clearCache();

        $product = ['price' => 100.00, 'currency' => 'USD'];
        $shippingQuote = ['amount' => 20.00, 'currency' => 'USD', 'estimated' => false, 'notes' => []];
        $result = $this->service->build($product, $shippingQuote, 1);

        $this->assertNotNull($result);
        $arr = $result->toArray();
        $this->assertSame(12.0, $arr['markup_amount']); // 10% of 120
        $this->assertSame(120.0, $arr['subtotal']);
        $this->assertSame(132.0, $arr['final_total']);
    }

    public function test_estimated_shipping_propagation(): void
    {
        $product = ['price' => 25.00, 'currency' => 'USD'];
        $shippingQuote = [
            'amount' => 10.00,
            'currency' => 'USD',
            'estimated' => true,
            'notes' => ['Quote is estimated: missing or incomplete length, width, height'],
        ];
        $result = $this->service->build($product, $shippingQuote, 1);

        $this->assertNotNull($result);
        $arr = $result->toArray();
        $this->assertTrue($arr['estimated']);
        $this->assertNotEmpty($arr['notes']);
        $this->assertStringContainsString('estimated', implode(' ', $arr['notes']));
    }

    public function test_admin_configurable_pricing_usage(): void
    {
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_SERVICE_FEE, '2.5');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_PLATFORM_MARKUP_PERCENT, '5');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_MINIMUM_ORDER_THRESHOLD, '50');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_MINIMUM_ORDER_FEE, '3');
        ShippingSetting::clearCache();

        $product = ['price' => 10.00, 'currency' => 'USD'];
        $shippingQuote = ['amount' => 5.00, 'currency' => 'USD', 'estimated' => false, 'notes' => []];
        $result = $this->service->build($product, $shippingQuote, 1);

        $this->assertNotNull($result);
        $arr = $result->toArray();
        // subtotal 15 < 50 → minimum order fee 3 applied
        $this->assertSame(2.5, $arr['service_fee']);
        $this->assertSame(0.75, $arr['markup_amount']); // 5% of 15
        $this->assertSame(15.0, $arr['subtotal']);
        $this->assertSame(21.25, $arr['final_total']); // 15 + 2.5 + 0.75 + 3
    }

    public function test_quantity_multiplies_product_line(): void
    {
        $product = ['price' => 20.00, 'currency' => 'USD'];
        $shippingQuote = ['amount' => 12.00, 'currency' => 'USD', 'estimated' => false, 'notes' => []];
        $result = $this->service->build($product, $shippingQuote, 3);

        $this->assertNotNull($result);
        $arr = $result->toArray();
        $this->assertSame(20.0, $arr['product_price']);
        $this->assertSame(72.0, $arr['subtotal']); // 60 + 12
    }
}
