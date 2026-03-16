<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\ShippingCarrierRate;
use App\Models\ShippingCarrierZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCarrierRateCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_carrier_rate(): void
    {
        $admin = Admin::factory()->create();

        ShippingCarrierZone::query()->create([
            'carrier' => 'dhl',
            'origin_country' => null,
            'destination_country' => 'US',
            'zone_code' => 'Z1',
            'active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.config.shipping-rates.store'), [
                'carrier' => 'dhl',
                'zone_code' => 'Z1',
                'pricing_mode' => 'direct',
                'weight_min_kg' => 0,
                'weight_max_kg' => 10,
                'base_rate' => 25,
                'active' => 1,
            ])
            ->assertRedirect(route('admin.config.shipping-rates.index'));

        $rate = ShippingCarrierRate::query()->first();
        $this->assertNotNull($rate);
        $this->assertSame('dhl', $rate->carrier);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.config.shipping-rates.update', $rate), [
                'carrier' => 'dhl',
                'zone_code' => 'Z1',
                'pricing_mode' => 'warehouse',
                'weight_min_kg' => 0,
                'weight_max_kg' => 10,
                'base_rate' => 30,
                'active' => 1,
            ])
            ->assertRedirect(route('admin.config.shipping-rates.index'));

        $rate->refresh();
        $this->assertSame('warehouse', $rate->pricing_mode);
        $this->assertSame(30.0, $rate->base_rate);
    }
}

