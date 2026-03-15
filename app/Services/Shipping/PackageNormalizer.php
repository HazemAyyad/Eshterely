<?php

namespace App\Services\Shipping;

use Illuminate\Support\Str;

/**
 * Builds NormalizedPackageInput from raw array input.
 * Normalizes weight to kg and dimensions to cm.
 */
class PackageNormalizer
{
    public const WEIGHT_UNIT_KG = 'kg';
    public const WEIGHT_UNIT_LB = 'lb';
    public const DIMENSION_UNIT_CM = 'cm';
    public const DIMENSION_UNIT_IN = 'in';

    /**
     * @param  array{destination_country: string, carrier?: string|null, warehouse_mode?: bool, weight: float, weight_unit?: string, length: float, width: float, height: float, dimension_unit?: string, quantity?: int}  $input
     */
    public function normalize(array $input): NormalizedPackageInput
    {
        $weight = (float) ($input['weight'] ?? 0);
        $weightUnit = $this->normalizeWeightUnit($input['weight_unit'] ?? self::WEIGHT_UNIT_KG);
        $weightKg = $weightUnit === self::WEIGHT_UNIT_LB
            ? WeightConverter::lbToKg($weight)
            : $weight;

        $length = (float) ($input['length'] ?? 0);
        $width = (float) ($input['width'] ?? 0);
        $height = (float) ($input['height'] ?? 0);
        $dimUnit = $this->normalizeDimensionUnit($input['dimension_unit'] ?? self::DIMENSION_UNIT_CM);
        if ($dimUnit === self::DIMENSION_UNIT_IN) {
            $length = DimensionConverter::inToCm($length);
            $width = DimensionConverter::inToCm($width);
            $height = DimensionConverter::inToCm($height);
        }

        $destinationCountry = (string) ($input['destination_country'] ?? '');
        $destinationCountry = strtoupper(Str::limit($destinationCountry, 10, ''));
        $carrier = isset($input['carrier']) && $input['carrier'] !== '' && $input['carrier'] !== null
            ? (string) $input['carrier']
            : null;
        $warehouseMode = (bool) ($input['warehouse_mode'] ?? false);
        $quantity = (int) ($input['quantity'] ?? 1);
        $quantity = $quantity < 1 ? 1 : $quantity;

        return new NormalizedPackageInput(
            destinationCountry: $destinationCountry,
            carrier: $carrier,
            warehouseMode: $warehouseMode,
            weightKg: $weightKg,
            lengthCm: $length,
            widthCm: $width,
            heightCm: $height,
            quantity: $quantity
        );
    }

    /**
     * Normalize weight unit to kg or lb only. Do not store aliases (e.g. lbs → lb).
     */
    public function normalizeWeightUnit(?string $unit): string
    {
        $u = strtolower(trim((string) $unit));
        if ($u === 'lb' || $u === 'lbs' || $u === 'pound' || $u === 'pounds') {
            return self::WEIGHT_UNIT_LB;
        }

        return self::WEIGHT_UNIT_KG;
    }

    private function normalizeDimensionUnit(?string $unit): string
    {
        $u = strtolower(trim((string) $unit));
        if ($u === 'in' || $u === 'inch' || $u === 'inches') {
            return self::DIMENSION_UNIT_IN;
        }

        return self::DIMENSION_UNIT_CM;
    }
}
