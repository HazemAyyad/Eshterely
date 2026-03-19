<?php

namespace App\Services\Shipping;

use App\Models\ShippingSetting;

/**
 * Reads admin-configurable shipping values from shipping_settings.
 * All business values that may need admin control are read here with safe fallbacks.
 */
class ShippingPricingConfigService
{
    public const KEY_VOLUMETRIC_DIVISOR = 'volumetric_divisor';
    public const KEY_DEFAULT_CURRENCY = 'default_currency';
    public const KEY_DEFAULT_MARKUP_PERCENT = 'default_markup_percent';
    public const KEY_MIN_SHIPPING_CHARGE = 'min_shipping_charge';
    public const KEY_WAREHOUSE_HANDLING_FEE = 'warehouse_handling_fee';
    public const KEY_MULTI_PACKAGE_PERCENT = 'multi_package_percent';
    public const KEY_CARRIER_DISCOUNT_DHL = 'carrier_discount_dhl';
    public const KEY_CARRIER_DISCOUNT_UPS = 'carrier_discount_ups';
    public const KEY_CARRIER_DISCOUNT_FEDEX = 'carrier_discount_fedex';
    public const KEY_ROUNDING_STRATEGY = 'rounding_strategy';

    /** Final pricing layer (confirm screen / cart / order) */
    public const KEY_SERVICE_FEE = 'service_fee';
    public const KEY_PLATFORM_MARKUP_PERCENT = 'platform_markup_percent';
    public const KEY_MINIMUM_ORDER_FEE = 'minimum_order_fee';
    public const KEY_MINIMUM_ORDER_THRESHOLD = 'minimum_order_threshold';

    /** Fallback package defaults when product data is missing weight/dimensions */
    public const KEY_SHIPPING_DEFAULT_WEIGHT = 'shipping_default_weight';
    public const KEY_SHIPPING_DEFAULT_WEIGHT_UNIT = 'shipping_default_weight_unit';
    public const KEY_SHIPPING_DEFAULT_LENGTH = 'shipping_default_length';
    public const KEY_SHIPPING_DEFAULT_WIDTH = 'shipping_default_width';
    public const KEY_SHIPPING_DEFAULT_HEIGHT = 'shipping_default_height';
    public const KEY_SHIPPING_DEFAULT_DIMENSION_UNIT = 'shipping_default_dimension_unit';
    public const KEY_ORDER_NUMBER_PREFIX = 'order_number_prefix';

    public const ROUNDING_UP_500G = 'up_to_500g';
    public const ROUNDING_NEAREST_KG = 'nearest_kg';
    public const ROUNDING_NONE = 'none';

    /** Allowed values for rounding_strategy (validation and fallback checks). */
    public const ROUNDING_STRATEGIES = [
        self::ROUNDING_NONE,
        self::ROUNDING_NEAREST_KG,
        self::ROUNDING_UP_500G,
    ];

    /** Default fallback when not set. Documented for operations. */
    private const DEFAULT_VOLUMETRIC_DIVISOR = 5000.0;
    private const DEFAULT_CURRENCY = 'USD';
    private const DEFAULT_MARKUP_PERCENT = 0.0;
    private const DEFAULT_MIN_SHIPPING_CHARGE = 0.0;
    private const DEFAULT_WAREHOUSE_HANDLING_FEE = 0.0;
    private const DEFAULT_MULTI_PACKAGE_PERCENT = 0.0;
    private const DEFAULT_CARRIER_DISCOUNT = 0.0;
    private const DEFAULT_SERVICE_FEE = 0.0;
    private const DEFAULT_PLATFORM_MARKUP_PERCENT = 0.0;
    private const DEFAULT_MINIMUM_ORDER_FEE = 0.0;
    private const DEFAULT_MINIMUM_ORDER_THRESHOLD = 0.0;
    private const DEFAULT_FALLBACK_WEIGHT = 0.5;
    private const DEFAULT_FALLBACK_DIMENSION = 10.0;
    private const DEFAULT_ORDER_NUMBER_PREFIX = 'ZY';

    public function volumetricDivisor(): float
    {
        $v = ShippingSetting::getValue(self::KEY_VOLUMETRIC_DIVISOR);
        if ($v === null || $v === '') {
            return self::DEFAULT_VOLUMETRIC_DIVISOR;
        }
        $f = (float) $v;

        return $f > 0 ? $f : self::DEFAULT_VOLUMETRIC_DIVISOR;
    }

    public function defaultCurrency(): string
    {
        $v = ShippingSetting::getValue(self::KEY_DEFAULT_CURRENCY);

        return $v !== null && $v !== '' ? $v : self::DEFAULT_CURRENCY;
    }

    public function defaultMarkupPercent(): float
    {
        $v = ShippingSetting::getValue(self::KEY_DEFAULT_MARKUP_PERCENT);
        if ($v === null || $v === '') {
            return self::DEFAULT_MARKUP_PERCENT;
        }

        return (float) $v;
    }

    public function minShippingCharge(): float
    {
        $v = ShippingSetting::getValue(self::KEY_MIN_SHIPPING_CHARGE);
        if ($v === null || $v === '') {
            return self::DEFAULT_MIN_SHIPPING_CHARGE;
        }
        $f = (float) $v;

        return $f >= 0 ? $f : self::DEFAULT_MIN_SHIPPING_CHARGE;
    }

    public function warehouseHandlingFee(): float
    {
        $v = ShippingSetting::getValue(self::KEY_WAREHOUSE_HANDLING_FEE);
        if ($v === null || $v === '') {
            return self::DEFAULT_WAREHOUSE_HANDLING_FEE;
        }

        return (float) $v;
    }

    public function multiPackagePercent(): float
    {
        $v = ShippingSetting::getValue(self::KEY_MULTI_PACKAGE_PERCENT);
        if ($v === null || $v === '') {
            return self::DEFAULT_MULTI_PACKAGE_PERCENT;
        }

        return (float) $v;
    }

    public function carrierDiscountPercent(string $carrier): float
    {
        $key = match (strtoupper($carrier)) {
            'DHL' => self::KEY_CARRIER_DISCOUNT_DHL,
            'UPS' => self::KEY_CARRIER_DISCOUNT_UPS,
            'FEDEX' => self::KEY_CARRIER_DISCOUNT_FEDEX,
            default => null,
        };
        if ($key === null) {
            return self::DEFAULT_CARRIER_DISCOUNT;
        }
        $v = ShippingSetting::getValue($key);
        if ($v === null || $v === '') {
            return self::DEFAULT_CARRIER_DISCOUNT;
        }
        $f = (float) $v;

        return $f >= 0 && $f <= 100 ? $f : self::DEFAULT_CARRIER_DISCOUNT;
    }

    public function roundingStrategy(): string
    {
        $v = ShippingSetting::getValue(self::KEY_ROUNDING_STRATEGY);
        if ($v === null || $v === '') {
            return self::ROUNDING_NEAREST_KG;
        }
        return in_array($v, self::ROUNDING_STRATEGIES, true) ? $v : self::ROUNDING_NEAREST_KG;
    }

    public function serviceFee(): float
    {
        $v = ShippingSetting::getValue(self::KEY_SERVICE_FEE);
        if ($v === null || $v === '') {
            return self::DEFAULT_SERVICE_FEE;
        }
        $f = (float) $v;
        return $f >= 0 ? $f : self::DEFAULT_SERVICE_FEE;
    }

    public function platformMarkupPercent(): float
    {
        $v = ShippingSetting::getValue(self::KEY_PLATFORM_MARKUP_PERCENT);
        if ($v === null || $v === '') {
            return self::DEFAULT_PLATFORM_MARKUP_PERCENT;
        }
        return (float) $v;
    }

    public function minimumOrderFee(): float
    {
        $v = ShippingSetting::getValue(self::KEY_MINIMUM_ORDER_FEE);
        if ($v === null || $v === '') {
            return self::DEFAULT_MINIMUM_ORDER_FEE;
        }
        $f = (float) $v;
        return $f >= 0 ? $f : self::DEFAULT_MINIMUM_ORDER_FEE;
    }

    public function minimumOrderThreshold(): float
    {
        $v = ShippingSetting::getValue(self::KEY_MINIMUM_ORDER_THRESHOLD);
        if ($v === null || $v === '') {
            return self::DEFAULT_MINIMUM_ORDER_THRESHOLD;
        }
        $f = (float) $v;
        return $f >= 0 ? $f : self::DEFAULT_MINIMUM_ORDER_THRESHOLD;
    }

    /** Fallback weight value as stored (use with shippingDefaultWeightUnit). */
    public function shippingDefaultWeight(): float
    {
        $v = ShippingSetting::getValue(self::KEY_SHIPPING_DEFAULT_WEIGHT);
        if ($v === null || $v === '') {
            return self::DEFAULT_FALLBACK_WEIGHT;
        }
        $f = (float) $v;

        return $f > 0 ? $f : self::DEFAULT_FALLBACK_WEIGHT;
    }

    public function shippingDefaultWeightUnit(): string
    {
        $v = ShippingSetting::getValue(self::KEY_SHIPPING_DEFAULT_WEIGHT_UNIT);
        if ($v === null || $v === '') {
            return 'kg';
        }
        $u = strtolower(trim((string) $v));

        return $u === 'lb' || $u === 'lbs' ? 'lb' : 'kg';
    }

    /** Fallback length as stored (use with shippingDefaultDimensionUnit). */
    public function shippingDefaultLength(): float
    {
        return $this->shippingDefaultDimension(self::KEY_SHIPPING_DEFAULT_LENGTH);
    }

    public function shippingDefaultWidth(): float
    {
        return $this->shippingDefaultDimension(self::KEY_SHIPPING_DEFAULT_WIDTH);
    }

    public function shippingDefaultHeight(): float
    {
        return $this->shippingDefaultDimension(self::KEY_SHIPPING_DEFAULT_HEIGHT);
    }

    private function shippingDefaultDimension(string $key): float
    {
        $v = ShippingSetting::getValue($key);
        if ($v === null || $v === '') {
            return self::DEFAULT_FALLBACK_DIMENSION;
        }
        $f = (float) $v;

        return $f > 0 ? $f : self::DEFAULT_FALLBACK_DIMENSION;
    }

    public function shippingDefaultDimensionUnit(): string
    {
        $v = ShippingSetting::getValue(self::KEY_SHIPPING_DEFAULT_DIMENSION_UNIT);
        if ($v === null || $v === '') {
            return 'cm';
        }
        $u = strtolower(trim((string) $v));

        return $u === 'in' || $u === 'inch' || $u === 'inches' ? 'in' : 'cm';
    }

    public function orderNumberPrefix(): string
    {
        $v = ShippingSetting::getValue(self::KEY_ORDER_NUMBER_PREFIX);
        if ($v === null || trim($v) === '') {
            return self::DEFAULT_ORDER_NUMBER_PREFIX;
        }

        return strtoupper(trim($v));
    }

    /**
     * Snapshot of config values used for a quote (for transparency; avoid exposing sensitive data).
     */
    public function snapshotForQuote(): array
    {
        return [
            'volumetric_divisor' => $this->volumetricDivisor(),
            'default_currency' => $this->defaultCurrency(),
            'default_markup_percent' => $this->defaultMarkupPercent(),
            'min_shipping_charge' => $this->minShippingCharge(),
            'warehouse_handling_fee' => $this->warehouseHandlingFee(),
            'multi_package_percent' => $this->multiPackagePercent(),
            'rounding_strategy' => $this->roundingStrategy(),
        ];
    }

    /**
     * Snapshot of config values used for final pricing (confirm/cart/order).
     */
    public function snapshotForFinalPricing(): array
    {
        return [
            'service_fee' => $this->serviceFee(),
            'platform_markup_percent' => $this->platformMarkupPercent(),
            'minimum_order_fee' => $this->minimumOrderFee(),
            'minimum_order_threshold' => $this->minimumOrderThreshold(),
            'rounding_strategy' => $this->roundingStrategy(),
        ];
    }

    /**
     * All config keys that are editable from admin.
     */
    public static function editableKeys(): array
    {
        return [
            self::KEY_VOLUMETRIC_DIVISOR,
            self::KEY_DEFAULT_CURRENCY,
            self::KEY_DEFAULT_MARKUP_PERCENT,
            self::KEY_MIN_SHIPPING_CHARGE,
            self::KEY_WAREHOUSE_HANDLING_FEE,
            self::KEY_MULTI_PACKAGE_PERCENT,
            self::KEY_CARRIER_DISCOUNT_DHL,
            self::KEY_CARRIER_DISCOUNT_UPS,
            self::KEY_CARRIER_DISCOUNT_FEDEX,
            self::KEY_ROUNDING_STRATEGY,
            self::KEY_SERVICE_FEE,
            self::KEY_PLATFORM_MARKUP_PERCENT,
            self::KEY_MINIMUM_ORDER_FEE,
            self::KEY_MINIMUM_ORDER_THRESHOLD,
            self::KEY_SHIPPING_DEFAULT_WEIGHT,
            self::KEY_SHIPPING_DEFAULT_WEIGHT_UNIT,
            self::KEY_SHIPPING_DEFAULT_LENGTH,
            self::KEY_SHIPPING_DEFAULT_WIDTH,
            self::KEY_SHIPPING_DEFAULT_HEIGHT,
            self::KEY_SHIPPING_DEFAULT_DIMENSION_UNIT,
            self::KEY_ORDER_NUMBER_PREFIX,
        ];
    }
}
