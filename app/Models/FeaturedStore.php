<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeaturedStore extends Model
{
    protected $fillable = [
        'store_slug',
        'name',
        'description',
        'categories',
        'logo_url',
        'country_code',
        'store_url',
        'is_featured',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
