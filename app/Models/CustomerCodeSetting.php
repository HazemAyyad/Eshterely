<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton-style row: format for newly issued customer codes (prefix + zero-padded number).
 */
class CustomerCodeSetting extends Model
{
    protected $fillable = [
        'prefix',
        'numeric_padding',
    ];

    protected function casts(): array
    {
        return [
            'numeric_padding' => 'integer',
        ];
    }

    public static function current(): self
    {
        $row = static::query()->orderBy('id')->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'prefix' => 'ESH',
            'numeric_padding' => 5,
        ]);
    }
}
