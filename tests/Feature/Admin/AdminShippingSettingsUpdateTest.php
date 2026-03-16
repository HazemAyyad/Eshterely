<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\ShippingSetting;
use App\Models\ShippingSettingAudit;
use App\Services\Shipping\ShippingPricingConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShippingSettingsUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_shipping_settings_and_audit_is_recorded(): void
    {
        $admin = Admin::factory()->create();
        ShippingSetting::setValue(ShippingPricingConfigService::KEY_VOLUMETRIC_DIVISOR, '5000');

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.config.shipping-settings.update'), [
            'volumetric_divisor' => '6000',
        ]);

        $response->assertRedirect(route('admin.config.shipping-settings.edit'));

        $this->assertSame('6000', ShippingSetting::getValue(ShippingPricingConfigService::KEY_VOLUMETRIC_DIVISOR));
        $this->assertDatabaseHas('shipping_setting_audits', [
            'key' => ShippingPricingConfigService::KEY_VOLUMETRIC_DIVISOR,
            'old_value' => '5000',
            'new_value' => '6000',
            'admin_id' => $admin->id,
        ]);
    }
}

