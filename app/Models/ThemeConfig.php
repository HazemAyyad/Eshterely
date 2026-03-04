<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeConfig extends Model
{
    protected $table = 'theme_config';

    protected $fillable = [
        'primary_color',
        'background_color',
        'text_color',
        'muted_text_color',
    ];
}
