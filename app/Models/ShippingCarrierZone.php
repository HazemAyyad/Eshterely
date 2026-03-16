<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingCarrierZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'carrier',
        'origin_country',
        'destination_country',
        'zone_code',
        'active',
        'created_by_admin_id',
        'updated_by_admin_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}

