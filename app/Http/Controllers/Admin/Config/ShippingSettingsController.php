<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\ShippingSetting;
use App\Services\Shipping\ShippingPricingConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShippingSettingsController extends Controller
{
    public function edit(): View
    {
        $keys = ShippingPricingConfigService::editableKeys();
        $settings = ShippingSetting::getAllAsMap();
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $settings[$key] ?? $this->defaultForKey($key);
        }

        return view('admin.config.shipping-settings.edit', [
            'values' => $values,
            'roundingOptions' => [
                ShippingPricingConfigService::ROUNDING_NONE => __('admin.shipping_rounding_none'),
                ShippingPricingConfigService::ROUNDING_NEAREST_KG => __('admin.shipping_rounding_nearest_kg'),
                ShippingPricingConfigService::ROUNDING_UP_500G => __('admin.shipping_rounding_up_500g'),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $keys = ShippingPricingConfigService::editableKeys();
        $rules = [];
        foreach ($keys as $key) {
            $rules[$key] = 'nullable|string|max:255';
        }
        $validated = $request->validate($rules);

        foreach ($keys as $key) {
            $value = $validated[$key] ?? null;
            ShippingSetting::setValue($key, $value !== '' ? $value : null);
        }
        ShippingSetting::clearCache();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.shipping_settings_saved')]);
        }

        return redirect()->route('admin.config.shipping-settings.edit')->with('success', __('admin.shipping_settings_saved'));
    }

    private function defaultForKey(string $key): string
    {
        return match ($key) {
            ShippingPricingConfigService::KEY_VOLUMETRIC_DIVISOR => '5000',
            ShippingPricingConfigService::KEY_DEFAULT_CURRENCY => 'USD',
            ShippingPricingConfigService::KEY_DEFAULT_MARKUP_PERCENT => '0',
            ShippingPricingConfigService::KEY_MIN_SHIPPING_CHARGE => '0',
            ShippingPricingConfigService::KEY_WAREHOUSE_HANDLING_FEE => '0',
            ShippingPricingConfigService::KEY_MULTI_PACKAGE_PERCENT => '0',
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_DHL,
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_UPS,
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_FEDEX => '0',
            ShippingPricingConfigService::KEY_ROUNDING_STRATEGY => ShippingPricingConfigService::ROUNDING_NEAREST_KG,
            default => '',
        };
    }
}
