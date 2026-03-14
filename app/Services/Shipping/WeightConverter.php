<?php

namespace App\Services\Shipping;

/**
 * Centralized weight conversion between kg and lb.
 * Reference: 1 kg = 2.20462 lb (international avoirdupois).
 */
final class WeightConverter
{
    private const KG_TO_LB = 2.20462;

    public static function kgToLb(float $kg): float
    {
        return $kg * self::KG_TO_LB;
    }

    public static function lbToKg(float $lb): float
    {
        return $lb / self::KG_TO_LB;
    }
}
