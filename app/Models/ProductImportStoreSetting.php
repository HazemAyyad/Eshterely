<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImportStoreSetting extends Model
{
    protected $fillable = [
        'store_key',
        'display_name',
        'is_enabled',
        'free_attempts_enabled',
        'allow_ai_extraction',
        'allow_playwright_fallback',
        'playwright_priority',
        'playwright_timeout_seconds',
        'allow_paid_fallback',
        'paid_provider',
        'paid_provider_priority',
        'attempt_order',
        'max_retries',
        'timeout_seconds',
        'minimum_confidence',
        'requires_manual_review_for_missing_specs',
    ];

    protected $casts = [
        'is_enabled'               => 'boolean',
        'free_attempts_enabled'    => 'boolean',
        'allow_ai_extraction'      => 'boolean',
        'allow_playwright_fallback' => 'boolean',
        'allow_paid_fallback'      => 'boolean',
        'requires_manual_review_for_missing_specs' => 'boolean',
        'attempt_order'            => 'array',
        'minimum_confidence'       => 'float',
    ];

    /**
     * Fetch settings for a specific store. Returns null if not found.
     */
    public static function forStore(string $storeKey): ?self
    {
        return static::query()->where('store_key', $storeKey)->first();
    }

    /**
     * Ordered attempt list. Falls back to the standard pipeline order.
     *
     * @return string[]
     */
    public function attemptOrder(): array
    {
        return $this->attempt_order ?? [
            'structured_data',
            'json_ld',
            'open_graph',
            'direct_html',
            'ai_extraction',
            'playwright',
            'paid_scraper',
        ];
    }
}
