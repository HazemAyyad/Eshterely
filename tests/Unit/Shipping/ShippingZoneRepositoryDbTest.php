<?php

namespace Tests\Unit\Shipping;

use App\Models\ShippingCarrierRate;
use App\Models\ShippingCarrierZone;
use App\Services\Shipping\ShippingZoneRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingZoneRepositoryDbTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_zone_and_base_price_from_db(): void
    {
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
            'weight_max_kg' => 5.0,
            'base_rate' => 20.0,
            'active' => true,
        ]);

        $repo = new ShippingZoneRepository();
        $zone = $repo->resolveZone('dhl', 'US', null);
        $this->assertSame('Z1', $zone);

        $price = $repo->getBasePriceForWeight('dhl', 'Z1', 3.0);
        $this->assertSame(20.0, $price);
    }
}

