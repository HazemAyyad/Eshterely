<?php

namespace Tests\Unit\Shipping;

use App\Models\ShippingCarrierRate;
use App\Models\ShippingCarrierZone;
use App\Models\ShippingSetting;
use App\Services\Shipping\CarrierPricingContext;
use App\Services\Shipping\Resolvers\DhlPricingResolver;
use App\Services\Shipping\ShippingPricingConfigService;
use App\Services\Shipping\ShippingZoneRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarrierResolverZoneIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dhl_resolver_uses_zone_base_rate_when_configured(): void
    {
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_MIN_SHIPPING_CHARGE, '0');
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_WAREHOUSE_HANDLING_FEE, '0');
        ShippingSetting::clearCache();

        ShippingCarrierZone::query()->create([
            'carrier' => 'dhl',
            'origin_country' => null,
            'destination_country' => 'US',
            'zone_code' => 'Z1',
            'active' => true,
        ]);

        ShippingCarrierRate::query()->create([
            'carrier' => 'dhl',
            'zone_code' => 'Z1',
            'pricing_mode' => 'direct',
            'weight_min_kg' => 0.0,
            'weight_max_kg' => 10.0,
            'base_rate' => 15.0,
            'active' => true,
        ]);

        $config = new ShippingPricingConfigService();
        $zones = new ShippingZoneRepository();
        $resolver = new DhlPricingResolver($config, $zones);

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

        $this->assertEqualsWithDelta(15.0, $result->amount, 0.001);
        $this->assertSame('dhl_zone', $result->pricingMode);
        $this->assertEquals(15.0, $result->breakdown['base_from_weight']);
    }
}

