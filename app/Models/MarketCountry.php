<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketCountry extends Model
{
    protected $table = 'market_countries';

    protected $fillable = [
        'code',
        'name',
        'flag_emoji',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
        ];
    }
}
