<?php

namespace App\Http\Resources;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    /**
     * Normalize address contract for Flutter:
     * - stable types across list/store/update
     * - keep country_id / city_id as string codes (backward compatible with existing list response)
     * - also expose numeric DB ids as country_db_id / city_db_id
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Address $a */
        $a = $this->resource;

        $countryCode = $a->country?->code;
        $cityCode = $a->city?->code;

        $addressLine = $a->address_line ?? trim(implode(', ', array_filter([
            $a->street_address,
            $a->area_district,
            $a->city?->name,
            $a->country?->name,
        ])));

        return [
            'id' => (string) $a->id,
            'address_line' => $addressLine,

            // Backward compatible: code strings (not DB ids)
            'country_id' => (string) ($countryCode ?? (string) $a->country_id),
            'country_name' => (string) ($a->country?->name ?? ''),
            'city_id' => $cityCode !== null ? (string) $cityCode : ($a->city_id !== null ? (string) $a->city_id : ''),
            'city_name' => (string) ($a->city?->name ?? ''),

            // Explicit DB ids for safe parsing
            'country_db_id' => $a->country_id !== null ? (int) $a->country_id : null,
            'city_db_id' => $a->city_id !== null ? (int) $a->city_id : null,
            'country_code' => $countryCode,
            'city_code' => $cityCode,

            'phone' => $a->phone !== null ? (string) $a->phone : null,
            'is_default' => (bool) $a->is_default,
            'nickname' => $a->nickname,
            'address_type' => $a->address_type,
            'area_district' => $a->area_district,
            'street_address' => $a->street_address,
            'building_villa_suite' => $a->building_villa_suite,
            'is_verified' => (bool) $a->is_verified,
            'is_residential' => (bool) $a->is_residential,
            'linked_to_active_order' => (bool) $a->linked_to_active_order,
            'is_locked' => (bool) $a->is_locked,
            'lat' => $a->lat !== null ? (float) $a->lat : null,
            'lng' => $a->lng !== null ? (float) $a->lng : null,
        ];
    }
}

