<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImportLog extends Model
{
    protected $fillable = [
        'url',
        'store_key',
        'provider',
        'attempt_index',
        'success',
        'partial_success',
        'confidence',
        'missing_fields',
        'response_snapshot',
        'error_message',
        'used_paid_provider',
    ];

    protected $casts = [
        'success'           => 'boolean',
        'partial_success'   => 'boolean',
        'used_paid_provider' => 'boolean',
        'missing_fields'    => 'array',
        'response_snapshot' => 'array',
        'confidence'        => 'float',
    ];
}
