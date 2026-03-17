<?php

namespace Tests\Unit\Shipping;

use App\Services\Shipping\ShippingZoneRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingZoneRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_zone_returns_null_without_data(): void
    {
        $repo = new ShippingZoneRepository();
        $this->assertNull($repo->resolveZone('dhl', 'US', null));
        $this->assertNull($repo->resolveZone('ups', 'SA', 'US'));
    }

    public function test_get_base_price_for_weight_returns_zero_without_rates(): void
    {
        $repo = new ShippingZoneRepository();
        $this->assertSame(0.0, $repo->getBasePriceForWeight('dhl', 'zone_a', 5.0));
    }

    public function test_get_zone_rate_info_returns_null_without_data(): void
    {
        $repo = new ShippingZoneRepository();
        $this->assertNull($repo->getZoneRateInfo('dhl', 'US', 'DE', 2.5));
    }
}
