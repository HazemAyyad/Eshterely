<?php

namespace App\Services\ProductImport;

use App\Services\ProductExtractionService;
use App\Services\ProductPageFetcherService;
use App\Services\StructuredProductImportService;
use Illuminate\Support\Facades\Log;

class ImportOrchestrator
{
    public function __construct(
        private ProductPageFetcherService $pageFetcher,
        private ProductExtractionService $extractionService,
        private StructuredProductImportService $structuredService,
        private AiProductParserService $aiParser,
        private ResultMerger $merger,
    ) {}

    /**
     * Run the import pipeline without breaking existing flows.
     *
     * Pipeline order:
     *  1) Existing logic (fetchHtml + extract HTML pipeline / structured per current services)
     *  2) HTML/meta parsing (inside ProductExtractionService)
     *  3) Store-specific parsing (inside DOM selectors / structured mappers)
     *  4) ScraperAPI (Amazon structured first for Amazon only, else optional)
     *  5) AI fallback (parser only; strict JSON; no measurements)
     *
     * @param  array{extraction_strategy?: string}  $options
     * @return array{product: array<string,mixed>, debug: array<string,mixed>}
     */
    public function import(string $url, string $storeKey, array $options = []): array
    {
        $attempts = [];
        $warnings = [];
        $debug = [
            'store_detected' => $storeKey,
            'asin' => null,
            'provider_used' => null,
            'provider_attempts' => &$attempts,
            'warnings' => &$warnings,
            'scraperapi_raw' => null,
            'ai_parsed_json' => null,
        ];

        $storeKeyLower = strtolower($storeKey);
        if ($storeKeyLower === 'amazon') {
            $debug['asin'] = $this->structuredService->extractAmazonAsin($url);
        }

        // 1) Existing logic: fetch + HTML pipeline extraction (may delegate to structured_api sentinel for Amazon)
        $fetch = $this->pageFetcher->fetchHtml($url, $storeKeyLower);
        $attempts[] = [
            'provider' => $fetch['fetch_source'] ?? 'direct_http',
            'stage' => 'fetch',
            'success' => ($fetch['html'] ?? '') !== '' || (($fetch['html_strategy'] ?? '') === 'structured_api'),
            'note' => $fetch['html_strategy'] ?? null,
        ];

        $html = (string) ($fetch['html'] ?? '');
        $fetchMetadata = [
            'fetch_source' => $fetch['fetch_source'] ?? 'direct_http',
            'html_strategy' => $fetch['html_strategy'] ?? 'initial_html',
            'blocked_or_captcha' => $fetch['blocked_or_captcha'] ?? false,
        ];

        $strategy = (string) ($options['extraction_strategy'] ?? 'auto');
        $existing = $this->extractionService->extract($html, $url, $storeKeyLower, $strategy, $fetchMetadata);
        $attempts[] = [
            'provider' => $existing['extraction_source'] ?? 'unknown',
            'stage' => 'existing_logic',
            'success' => ($existing['name'] ?? '') !== '' && strtolower((string) ($existing['name'] ?? '')) !== 'product',
            'note' => 'price=' . ($existing['price'] ?? 0),
        ];

        $product = $existing;
        $debug['provider_used'] = $existing['extraction_source'] ?? 'unknown';

        // 4) ScraperAPI structured (Amazon ONLY first)
        // NOTE: ProductExtractionService already prioritizes Amazon structured when key exists,
        // but we keep an explicit stage here for traceability and to satisfy the pipeline contract.
        if ($storeKeyLower === 'amazon' && ! empty(config('services.product_import.scraperapi_key'))) {
            $attempts[] = [
                'provider' => 'scraperapi_structured_amazon',
                'stage' => 'scraperapi',
                'success' => ($product['extraction_source'] ?? '') === 'amazon_structured_api',
                'note' => ($product['extraction_source'] ?? '') === 'amazon_structured_api'
                    ? 'Used as primary provider (existing services)'
                    : 'Not used (no key or failed earlier)',
            ];

            if (($product['extraction_source'] ?? '') === 'amazon_structured_api') {
                $debug['scraperapi_raw'] = $product['scraperapi_raw'] ?? null;
            }
        }

        if ($storeKeyLower === 'walmart' && ! empty(config('services.product_import.scraperapi_key'))) {
            $src = (string) ($product['extraction_source'] ?? '');
            $walmartStructured = in_array($src, ['walmart_structured_api', 'html_structured_merged'], true);
            $attempts[] = [
                'provider' => 'scraperapi_structured_walmart',
                'stage' => 'scraperapi',
                'success' => $walmartStructured,
                'note' => $walmartStructured
                    ? 'Structured API used (primary or merged with HTML)'
                    : 'Not used (HTML considered complete without measurements, no key, or API failed)',
            ];
            if ($walmartStructured && ! empty($product['scraperapi_raw'])) {
                $debug['scraperapi_raw'] = $product['scraperapi_raw'];
            }
        }

        // 5) AI fallback parser (only if we still look incomplete)
        $needsAi = $this->needsAiFallback($product);
        if ($needsAi) {
            $reduced = $this->extractionService->buildReducedPayload($html, $url);
            $ai = $this->aiParser->parse($reduced);
            $attempts[] = [
                'provider' => 'ai_parser',
                'stage' => 'ai_fallback',
                'success' => is_array($ai) && (($ai['title'] ?? null) !== null || ($ai['price'] ?? null) !== null || ($ai['image'] ?? null) !== null),
                'note' => $ai ? 'parsed' : 'skipped_or_failed',
            ];

            if (is_array($ai)) {
                $debug['ai_parsed_json'] = $ai;
                $mapped = $this->mapAiToNormalized($ai, $storeKeyLower, $url);
                $product = $this->merger->merge($product, $mapped);
                $debug['provider_used'] = 'ai_parser_merged';
            }
        }

        // Normalize presence of measurements fields (do not invent).
        if (! array_key_exists('weight', $product)) {
            $product['weight'] = null;
        }
        if (! array_key_exists('dimensions', $product)) {
            $product['dimensions'] = null;
        }

        $hasMeasurements = $product['weight'] !== null && $product['dimensions'] !== null;
        $product['measurements_found'] = $hasMeasurements;
        $product['shipping_estimate_source'] = $hasMeasurements ? 'exact' : 'fallback';

        // Normalized measurement output (explicit, nullable; do not guess).
        // Keep legacy keys for shipping engine compatibility.
        $product['weight_value'] = is_numeric($product['weight']) ? (float) $product['weight'] : null;
        $product['weight_unit'] = $product['weight_unit'] ?? null;
        $product['dimensions_length'] = is_numeric($product['length'] ?? null) ? (float) $product['length'] : null;
        $product['dimensions_width'] = is_numeric($product['width'] ?? null) ? (float) $product['width'] : null;
        $product['dimensions_height'] = is_numeric($product['height'] ?? null) ? (float) $product['height'] : null;
        $product['dimensions_unit'] = $product['dimension_unit'] ?? ($product['dimensions']['unit'] ?? null);

        $product['has_exact_measurements'] = (bool) ($product['has_exact_measurements'] ?? false);
        $product['measurements_source'] = $product['measurements_source'] ?? null;
        $product['measurements_source_fields'] = $product['measurements_source_fields'] ?? null;

        // If we have full numeric measurements but no explicit exact marker, keep conservative false.
        // Exact measurements should only be marked true when explicitly provided by structured sources.
        if ($product['has_exact_measurements'] === false && $hasMeasurements) {
            $product['has_exact_measurements'] = false;
        }

        if (! $hasMeasurements) {
            $warnings[] = 'Measurements missing — shipping estimate uses fallback defaults.';
        }

        Log::info('ProductImport pipeline complete', [
            'store_key' => $storeKeyLower,
            'provider_used' => $debug['provider_used'],
            'attempts' => array_map(fn ($a) => $a['provider'] ?? null, $attempts),
            'measurements_found' => $hasMeasurements,
            'shipping_source' => $product['shipping_estimate_source'],
        ]);

        return ['product' => $product, 'debug' => $debug];
    }

    /**
     * Decide if the AI fallback is worth trying.
     *
     * @param  array<string, mixed>  $product
     */
    private function needsAiFallback(array $product): bool
    {
        $name = trim((string) ($product['name'] ?? ''));
        $price = (float) ($product['price'] ?? 0);
        $img = trim((string) ($product['image_url'] ?? ''));

        if ($name === '' || strtolower($name) === 'product') {
            return true;
        }
        if ($price <= 0) {
            return true;
        }
        if ($img === '') {
            return true;
        }
        return false;
    }

    /**
     * Map AI output to existing normalized schema keys.
     *
     * @param  array<string, mixed>  $ai
     * @return array<string, mixed>
     */
    private function mapAiToNormalized(array $ai, string $storeKey, string $url): array
    {
        $name = isset($ai['title']) && is_string($ai['title']) ? trim($ai['title']) : null;
        $price = $ai['price'] ?? null;
        $image = isset($ai['image']) && is_string($ai['image']) ? $ai['image'] : null;

        $out = [
            'canonical_url' => $url,
            'store_key' => $storeKey,
            'extraction_source' => 'ai_parser',
        ];

        if ($name !== null && $name !== '') {
            $out['name'] = $name;
        }
        if (is_numeric($price) && (float) $price > 0) {
            $out['price'] = (float) $price;
        }
        if ($image !== null && filter_var($image, FILTER_VALIDATE_URL)) {
            $out['image_url'] = $image;
        }

        return $out;
    }
}

