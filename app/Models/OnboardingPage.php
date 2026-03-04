<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingPage extends Model
{
    protected $fillable = [
        'sort_order',
        'image_url',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
