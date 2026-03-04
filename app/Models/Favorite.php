<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    protected $fillable = ['user_id', 'source_key', 'source_label', 'title', 'price', 'currency', 'price_drop', 'tracking_on', 'stock_status', 'stock_label', 'image_url', 'product_url'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_drop' => 'decimal:2',
            'tracking_on' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
