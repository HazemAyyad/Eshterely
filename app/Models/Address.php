<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = [
        'user_id', 'country_id', 'city_id', 'nickname', 'address_type',
        'area_district', 'street_address', 'building_villa_suite', 'address_line',
        'phone', 'is_default', 'is_verified', 'is_residential',
        'lat', 'lng', 'linked_to_active_order', 'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_verified' => 'boolean',
            'is_residential' => 'boolean',
            'linked_to_active_order' => 'boolean',
            'is_locked' => 'boolean',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }
}
