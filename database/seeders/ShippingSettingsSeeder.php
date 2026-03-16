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
            // Core calculation behaviour
            ShippingPricingConfigService::KEY_VOLUMETRIC_DIVISOR => '5000', // Common operational default for many carriers
            ShippingPricingConfigService::KEY_DEFAULT_CURRENCY => 'USD',
            ShippingPricingConfigService::KEY_DEFAULT_MARKUP_PERCENT => '5', // Starter markup; operations should review
            ShippingPricingConfigService::KEY_MIN_SHIPPING_CHARGE => '5', // Avoid near-zero shipping on very small orders
            ShippingPricingConfigService::KEY_WAREHOUSE_HANDLING_FEE => '3', // Starter warehouse handling fee per shipment
            ShippingPricingConfigService::KEY_MULTI_PACKAGE_PERCENT => '10', // Extra % for multi-package shipments

            // Carrier-specific discounts (business can tune per contract)
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_DHL => '5',
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_UPS => '3',
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_FEDEX => '4',

            ShippingPricingConfigService::KEY_ROUNDING_STRATEGY => ShippingPricingConfigService::ROUNDING_NEAREST_KG,

            // Final pricing layer (cart / confirm)
            ShippingPricingConfigService::KEY_SERVICE_FEE => '1.5',
            ShippingPricingConfigService::KEY_PLATFORM_MARKUP_PERCENT => '3',
            ShippingPricingConfigService::KEY_MINIMUM_ORDER_FEE => '0',
            ShippingPricingConfigService::KEY_MINIMUM_ORDER_THRESHOLD => '0',

            // Fallback package defaults
            ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_WEIGHT => '0.5', // 0.5 kg small parcel
            ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_WEIGHT_UNIT => 'kg',
            ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_LENGTH => '20',
            ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_WIDTH => '15',
            ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_HEIGHT => '8',
            ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_DIMENSION_UNIT => 'cm',
        ];

        foreach ($defaults as $key => $value) {
            if (! ShippingSetting::query()->where('key', $key)->exists()) {
                ShippingSetting::setValue($key, $value);
            }
        }
    }
}
