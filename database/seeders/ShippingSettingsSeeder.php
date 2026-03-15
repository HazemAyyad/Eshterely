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
            ShippingPricingConfigService::KEY_SERVICE_FEE => '0',
            ShippingPricingConfigService::KEY_PLATFORM_MARKUP_PERCENT => '0',
            ShippingPricingConfigService::KEY_MINIMUM_ORDER_FEE => '0',
            ShippingPricingConfigService::KEY_MINIMUM_ORDER_THRESHOLD => '0',
            ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_WEIGHT => '0.5',
            ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_WEIGHT_UNIT => 'kg',
            ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_LENGTH => '10',
            ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_WIDTH => '10',
            ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_HEIGHT => '10',
            ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_DIMENSION_UNIT => 'cm',
        ];

        foreach ($defaults as $key => $value) {
            if (! ShippingSetting::query()->where('key', $key)->exists()) {
                ShippingSetting::setValue($key, $value);
            }
        }
    }
}
