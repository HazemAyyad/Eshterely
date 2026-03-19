<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\ShippingSetting;
use App\Models\ShippingSettingAudit;
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
        $rules = $this->validationRulesForKeys($keys);
        $validated = $request->validate($rules);
        $existing = ShippingSetting::getAllAsMap();
        $adminId = auth('admin')->id();

        foreach ($keys as $key) {
            $value = $validated[$key] ?? null;
            $normalized = $value !== '' ? $value : null;
            $old = $existing[$key] ?? null;
            if ($old !== $normalized) {
                ShippingSettingAudit::query()->create([
                    'key' => $key,
                    'old_value' => $old,
                    'new_value' => $normalized,
                    'admin_id' => $adminId,
                ]);
            }
            ShippingSetting::setValue($key, $normalized);
        }
        ShippingSetting::clearCache();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.shipping_settings_saved')]);
        }

        return redirect()->route('admin.config.shipping-settings.edit')->with('success', __('admin.shipping_settings_saved'));
    }

    /**
     * Validation rules per editable key: numeric settings use numeric|min:0,
     * rounding_strategy is restricted to allowed values, currency remains string.
     */
    private function validationRulesForKeys(array $keys): array
    {
        $numericKeys = [
            ShippingPricingConfigService::KEY_VOLUMETRIC_DIVISOR,
            ShippingPricingConfigService::KEY_DEFAULT_MARKUP_PERCENT,
            ShippingPricingConfigService::KEY_MIN_SHIPPING_CHARGE,
            ShippingPricingConfigService::KEY_WAREHOUSE_HANDLING_FEE,
            ShippingPricingConfigService::KEY_MULTI_PACKAGE_PERCENT,
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_DHL,
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_UPS,
            ShippingPricingConfigService::KEY_CARRIER_DISCOUNT_FEDEX,
            ShippingPricingConfigService::KEY_SERVICE_FEE,
            ShippingPricingConfigService::KEY_PLATFORM_MARKUP_PERCENT,
            ShippingPricingConfigService::KEY_MINIMUM_ORDER_FEE,
            ShippingPricingConfigService::KEY_MINIMUM_ORDER_THRESHOLD,
            ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_WEIGHT,
            ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_LENGTH,
            ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_WIDTH,
            ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_HEIGHT,
        ];
        $roundingKey = ShippingPricingConfigService::KEY_ROUNDING_STRATEGY;
        $roundingAllowed = implode(',', ShippingPricingConfigService::ROUNDING_STRATEGIES);
        $weightUnitKey = ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_WEIGHT_UNIT;
        $dimensionUnitKey = ShippingPricingConfigService::KEY_SHIPPING_DEFAULT_DIMENSION_UNIT;

        $rules = [];
        foreach ($keys as $key) {
            if (in_array($key, $numericKeys, true)) {
                $rules[$key] = 'nullable|numeric|min:0';
            } elseif ($key === $roundingKey) {
                $rules[$key] = 'nullable|in:' . $roundingAllowed;
            } elseif ($key === $weightUnitKey) {
                $rules[$key] = 'nullable|string|in:kg,lb,lbs';
            } elseif ($key === $dimensionUnitKey) {
                $rules[$key] = 'nullable|string|in:cm,in';
            } else {
                $rules[$key] = 'nullable|string|max:255';
            }
        }

        return $rules;
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
            ShippingPricingConfigService::KEY_ORDER_NUMBER_PREFIX => 'ZY',
            default => '',
        };
    }
}
