<?php

namespace App\Services\ProductImport;

use Illuminate\Support\Str;

/**
 * Resolves a store_key from a product URL.
 * Used by ProductImportController and ImportAttemptOrchestrator.
 */
class StoreResolver
{
    private const STORE_MAP = [
        'amazon.'      => 'amazon',
        'ebay.'        => 'ebay',
        'walmart.'     => 'walmart',
        'etsy.'        => 'etsy',
        'aliexpress.'  => 'aliexpress',
        'trendyol.'    => 'trendyol',
        'noon.'        => 'noon',
        'temu.'        => 'temu',
        'shein.'       => 'shein',
        'shopify.'     => 'shopify',
        'macys.'       => 'macys',
        'iherb.'       => 'iherb',
        'sephora.'     => 'sephora',
    ];

    /**
     * Detect store_key from URL. Returns 'generic' for unknown stores.
     */
    public static function resolve(string $url): string
    {
        foreach (self::STORE_MAP as $pattern => $key) {
            if (Str::contains(strtolower($url), $pattern)) {
                return $key;
            }
        }

        // WooCommerce detection: look for /product/ or /?product= in path
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if (Str::contains($path, '/product/') || Str::contains($path, '/shop/')) {
            return 'woocommerce';
        }

        return 'generic';
    }

    /**
     * All known store keys.
     *
     * @return string[]
     */
    public static function knownStores(): array
    {
        return array_merge(array_values(self::STORE_MAP), ['woocommerce', 'generic']);
    }
}
