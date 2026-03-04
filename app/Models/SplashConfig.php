<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SplashConfig extends Model
{
    protected $table = 'splash_config';

    protected $fillable = [
        'logo_url',
        'title_en',
        'title_ar',
        'subtitle_en',
        'subtitle_ar',
        'progress_text_en',
        'progress_text_ar',
    ];
}
