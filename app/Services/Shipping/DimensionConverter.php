<?php

namespace App\Services\Shipping;

/**
 * Centralized dimension conversion between cm and inches.
 * Reference: 1 in = 2.54 cm.
 */
final class DimensionConverter
{
    private const CM_PER_INCH = 2.54;

    public static function cmToIn(float $cm): float
    {
        return $cm / self::CM_PER_INCH;
    }

    public static function inToCm(float $inches): float
    {
        return $inches * self::CM_PER_INCH;
    }
}
