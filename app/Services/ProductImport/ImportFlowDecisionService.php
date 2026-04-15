<?php

namespace App\Services\ProductImport;

use App\Models\FeaturedStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Decides API `import_flow` for Add via Link: only active featured stores use the standard import/cart path.
 */
class ImportFlowDecisionService
{
    public const ACTIVE_SLUGS_CACHE_KEY = 'product_import:active_featured_store_slugs';

    public const ACTIVE_SLUGS_CACHE_TTL_SECONDS = 300;

    /**
     * @return 'standard'|'purchase_assistant'
     */
    public function importFlowForResolvedStoreKey(string $storeKey): string
    {
        $key = strtolower(trim($storeKey));
        if ($key === '') {
            return 'purchase_assistant';
        }

        $allowed = $this->activeFeaturedStoreSlugsLowercased();
        if ($allowed === []) {
            Log::warning('product_import: no active featured stores configured; import_flow falls back to purchase_assistant for all URLs', [
                'store_key' => $key,
            ]);

            return 'purchase_assistant';
        }

        return in_array($key, $allowed, true) ? 'standard' : 'purchase_assistant';
    }

    /**
     * Lowercased `store_slug` values from `featured_stores` where `is_active` is true (admin featured-stores config).
     *
     * @return list<string>
     */
    public function activeFeaturedStoreSlugsLowercased(): array
    {
        if (! Schema::hasTable('featured_stores')) {
            return [];
        }

        return Cache::remember(
            self::ACTIVE_SLUGS_CACHE_KEY,
            self::ACTIVE_SLUGS_CACHE_TTL_SECONDS,
            function (): array {
                return FeaturedStore::query()
                    ->where('is_active', true)
                    ->pluck('store_slug')
                    ->map(fn ($s) => strtolower(trim((string) $s)))
                    ->filter(fn ($s) => $s !== '')
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
            }
        );
    }

    public function forgetActiveSlugsCache(): void
    {
        Cache::forget(self::ACTIVE_SLUGS_CACHE_KEY);
    }
}
