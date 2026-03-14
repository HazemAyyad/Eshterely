<?php

namespace Database\Seeders;

use App\Models\ShippingSetting;
use App\Services\Shipping\ShippingPricingConfigService;
use Illuminate\Database\Seeder;

class ShippingSettingsSeeder extends Seeder
{
    /**
     * Seed default shipping settings. Only inserts if key is missing.
     */
    public function run(): void
    {
        $defaults = [
            ShippingPricingConfigService::KEY_VOLUMETRIC_DIVISOR => '5000',
            ShippingPricingConfigService::KEY_DEFAULT_CURRENCY => 'USD',
            ShippingPricingConfigService::KEY_DEFAULT_MARKUP_PERCENT => '0',
            ShippingPricingConfigService::KEY_MIN_SHIPPING_CHARGE => '0',
            ShippingPricingConfigService::KEY_WAREHOUSE_HANDLING_FEE => '0',
            ShippingPricingConfigService::KEY_MULTI_PACKAGE_PERCENT => '0',
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_DHL => '0',
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_UPS => '0',
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_FEDEX => '0',
            ShippingPricingConfigService::KEY_ROUNDING_STRATEGY => ShippingPricingConfigService::ROUNDING_NEAREST_KG,
        ];

        foreach ($defaults as $key => $value) {
            if (! ShippingSetting::query()->where('key', $key)->exists()) {
                ShippingSetting::setValue($key, $value);
            }
        }
    }
}
