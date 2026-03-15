<?php

namespace Tests\Unit\Shipping;

use App\Models\ShippingSetting;
use App\Services\Shipping\CarrierPricingContext;
use App\Services\Shipping\CarrierPricingResolverRegistry;
use App\Services\Shipping\Resolvers\DhlPricingResolver;
use App\Services\Shipping\Resolvers\FedexPricingResolver;
use App\Services\Shipping\Resolvers\UpsPricingResolver;
use App\Services\Shipping\ShippingPricingConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarrierPricingResolverTest extends TestCase
{
    use RefreshDatabase;

    private CarrierPricingResolverRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedShippingSettings();
        $config = app(ShippingPricingConfigService::class);
        $this->registry = new CarrierPricingResolverRegistry();
        $this->registry->register(new DhlPricingResolver($config));
        $this->registry->register(new UpsPricingResolver($config));
        $this->registry->register(new FedexPricingResolver($config));
    }

    private function seedShippingSettings(): void
    {
        $keys = [
            ShippingPricingConfigService::KEY_MIN_SHIPPING_CHARGE => '5',
            ShippingPricingConfigService::KEY_WAREHOUSE_HANDLING_FEE => '2',
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_DHL => '10',
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_UPS => '0',
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_FEDEX => '5',
        ];
        foreach ($keys as $key => $value) {
            ShippingSetting::setValue($key, $value);
        }
        ShippingSetting::clearCache();
    }

    public function test_registry_returns_resolver_for_dhl_ups_fedex(): void
    {
        $this->assertNotNull($this->registry->get('dhl'));
        $this->assertNotNull($this->registry->get('ups'));
        $this->assertNotNull($this->registry->get('fedex'));
        $this->assertTrue($this->registry->get('dhl')->supportsCarrier('dhl'));
        $this->assertTrue($this->registry->get('ups')->supportsCarrier('ups'));
        $this->assertTrue($this->registry->get('fedex')->supportsCarrier('fedex'));
    }

    public function test_dhl_resolver_applies_warehouse_fee_when_warehouse_mode_true(): void
    {
        $resolver = $this->registry->get('dhl');
        $this->assertNotNull($resolver);

        $contextDirect = new CarrierPricingContext(
            carrier: 'dhl',
            destinationCountry: 'US',
            warehouseMode: false,
            actualWeightKg: 1.0,
            volumetricWeightKg: 2.0,
            chargeableWeightKg: 2.0,
            lengthCm: 10.0,
            widthCm: 10.0,
            heightCm: 10.0,
            quantity: 1,
        );
        $contextWarehouse = new CarrierPricingContext(
            carrier: 'dhl',
            destinationCountry: 'US',
            warehouseMode: true,
            actualWeightKg: 1.0,
            volumetricWeightKg: 2.0,
            chargeableWeightKg: 2.0,
            lengthCm: 10.0,
            widthCm: 10.0,
            heightCm: 10.0,
            quantity: 1,
        );

        $resultDirect = $resolver->resolve($contextDirect);
        $resultWarehouse = $resolver->resolve($contextWarehouse);

        $this->assertGreaterThan($resultDirect->amount, $resultWarehouse->amount);
        $this->assertStringContainsString('Warehouse', implode(' ', $resultWarehouse->notes));
    }

    public function test_carrier_specific_discount_applied(): void
    {
        $resolver = $this->registry->get('dhl');
        $this->assertNotNull($resolver);

        $context = new CarrierPricingContext(
            carrier: 'dhl',
            destinationCountry: 'US',
            warehouseMode: false,
            actualWeightKg: 1.0,
            volumetricWeightKg: 1.0,
            chargeableWeightKg: 1.0,
            lengthCm: 10.0,
            widthCm: 10.0,
            heightCm: 10.0,
            quantity: 1,
        );

        $result = $resolver->resolve($context);
        $this->assertArrayHasKey('discount_percent', $result->breakdown);
        $this->assertSame(10.0, $result->breakdown['discount_percent']);
    }

    public function test_supported_carriers_list(): void
    {
        $carriers = $this->registry->supportedCarriers();
        $this->assertContains('dhl', $carriers);
        $this->assertContains('ups', $carriers);
        $this->assertContains('fedex', $carriers);
    }
}
