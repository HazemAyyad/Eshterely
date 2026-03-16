<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingCarrierRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'carrier',
        'zone_code',
        'pricing_mode',
        'weight_min_kg',
        'weight_max_kg',
        'base_rate',
        'active',
        'created_by_admin_id',
        'updated_by_admin_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'weight_min_kg' => 'float',
            'weight_max_kg' => 'float',
            'base_rate' => 'float',
            'active' => 'boolean',
        ];
    }
}

