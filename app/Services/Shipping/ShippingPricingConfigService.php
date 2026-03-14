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

    /**
     * Snapshot of config values used for a quote (for transparency; avoid exposing sensitive data).
     */
    public function snapshotForQuote(): array
    {
        return [
            'volumetric_divisor' => $this->volumetricDivisor(),
            'default_currency' => $this->defaultCurrency(),
            'min_shipping_charge' => $this->minShippingCharge(),
            'warehouse_handling_fee' => $this->warehouseHandlingFee(),
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
        ];
    }
}
