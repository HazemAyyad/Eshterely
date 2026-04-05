<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Uses ScraperAPI structured/rendered endpoints for supported stores.
 * Returns normalized product data + full raw ScraperAPI response in scraperapi_raw.
 */
class StructuredProductImportService
{
    private const STRUCTURED_TIMEOUT = 45;

    private const AMAZON_STRUCTURED_TIMEOUT = 90;

    private const AMAZON_STRUCTURED_RETRIES = 2;

    private const AMAZON_STRUCTURED_RETRY_DELAY_SECONDS = 2;

    private const STORE_DISPLAY_NAMES = [
        'amazon' => 'Amazon',
        'ebay' => 'eBay',
        'walmart' => 'Walmart',
        'aliexpress' => 'AliExpress',
        'etsy' => 'Etsy',
        'trendyol' => 'Trendyol',
        'unknown' => 'Unknown',
    ];

    private const COUNTRY_BY_STORE = [
        'amazon' => 'USA',
        'ebay' => 'USA',
        'walmart' => 'USA',
        'aliexpress' => 'China',
        'etsy' => 'USA',
        'trendyol' => 'Turkey',
        'unknown' => 'Unknown',
    ];

    /**
     * Extract ASIN from Amazon URLs: /dp/{ASIN}, /dp/{ASIN}?, /gp/product/{ASIN}?, /product/{ASIN}?.
     * Supports long URLs with query string; also checks query param asin=.
     */
    public function extractAmazonAsin(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path !== null && $path !== '') {
            if (preg_match('#/(?:dp|gp/product|product)/([A-Z0-9]{10})(?:/|$)#i', $path, $m)) {
                $asin = strtoupper($m[1]);
                Log::debug('Amazon ASIN extracted', ['source' => 'path', 'asin' => $asin, 'url' => $url]);
                return $asin;
            }
        }
        if (preg_match('#(?:/dp/|/gp/product/|/product/)([A-Z0-9]{10})(?:/|$|\?)#i', $url, $m)) {
            $asin = strtoupper($m[1]);
            Log::debug('Amazon ASIN extracted', ['source' => 'full_url', 'asin' => $asin, 'url' => $url]);
            return $asin;
        }
        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && preg_match('/(?:^|&)asin=([A-Z0-9]{10})(?:&|$)/i', $query, $m)) {
            $asin = strtoupper($m[1]);
            Log::debug('Amazon ASIN extracted', ['source' => 'query', 'asin' => $asin, 'url' => $url]);
            return $asin;
        }
        Log::debug('Amazon ASIN not found', ['url' => $url]);
        return null;
    }

    /**
     * Extract ScraperAPI-valid TLD from Amazon URL. Only returns TLD values accepted by the structured API.
     */
    public function extractAmazonTld(string $url): string
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        if (Str::contains($host, 'amazon.co.uk')) {
            return 'co.uk';
        }
        if (Str::contains($host, 'amazon.com.au')) {
            return 'com.au';
        }
        if (Str::contains($host, 'amazon.com')) {
            return 'com';
        }
        if (Str::contains($host, 'amazon.ca')) {
            return 'ca';
        }
        if (Str::contains($host, 'amazon.de')) {
            return 'de';
        }
        if (Str::contains($host, 'amazon.es')) {
            return 'es';
        }
        if (Str::contains($host, 'amazon.fr')) {
            return 'fr';
        }
        if (Str::contains($host, 'amazon.it')) {
            return 'it';
        }
        if (Str::contains($host, 'amazon.ae')) {
            return 'ae';
        }
        return 'com';
    }

    /**
     * Extract eBay item ID from URLs like /itm/123456789012.
     */
    public function extractEbayItemId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null || $path === '') {
            return null;
        }
        if (preg_match('#/itm/(\d{8,14})#', $path, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Extract Walmart product ID from URL (e.g. /ip/5253396052 or /ip/Product-Name/123456?query=...).
     */
    public function extractWalmartProductId(string $url): ?string
    {
        return $this->extractWalmartProductIdFromUrl($url);
    }

    /**
     * Parse path only (ignore query string). Prefer /ip/[optional-slug/]numericId at end of path.
     */
    private function extractWalmartProductIdFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            Log::debug('Walmart product ID extraction', [
                'url'        => $url,
                'path'       => null,
                'product_id' => null,
            ]);

            return null;
        }

        $path = rtrim($path, '/');

        $productId = null;
        if (preg_match('~\/ip\/(?:[^\/]+\/)?(\d+)$~', $path, $m)) {
            $productId = $m[1];
        } elseif (preg_match('~\/(\d+)$~', $path, $m)) {
            $productId = $m[1];
        }

        Log::debug('Walmart product ID extraction', [
            'url'        => $url,
            'path'       => $path,
            'product_id' => $productId,
        ]);

        return $productId;
    }

    /**
     * Try Amazon structured product API. Returns normalized + scraperapi_raw or null on failure.
     *
     * @return array<string, mixed>|null
     */
    public function extractAmazonStructured(string $url): ?array
    {
        $asin = $this->extractAmazonAsin($url);
        if ($asin === null) {
            Log::debug('Amazon structured skipped: no ASIN', ['url' => $url]);
            return null;
        }

        $tld = $this->extractAmazonTld($url);
        $countryCode = $this->amazonCountryCodeFromTld($tld);
        $apiKey = config('services.product_import.scraperapi_key');
        if (empty($apiKey)) {
            Log::debug('Amazon structured skipped: no API key');
            return null;
        }

        $apiUrl = 'https://api.scraperapi.com/structured/amazon/product/v1?' . http_build_query([
            'api_key' => $apiKey,
            'asin' => $asin,
            'country_code' => $countryCode,
            'tld' => $tld,
        ]);

        $maxAttempts = self::AMAZON_STRUCTURED_RETRIES + 1;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                if ($attempt > 1) {
                    sleep(self::AMAZON_STRUCTURED_RETRY_DELAY_SECONDS);
                }

                $response = Http::timeout(self::AMAZON_STRUCTURED_TIMEOUT)->get($apiUrl);
                $status = $response->status();
                $body = $response->body();
                $raw = $response->json();
                if (! is_array($raw)) {
                    $raw = is_string($body) ? json_decode($body, true) : null;
                }

                Log::debug('Amazon structured API response', [
                    'asin' => $asin,
                    'tld' => $tld,
                    'attempt' => $attempt,
                    'status' => $status,
                    'body_length' => strlen($body ?? ''),
                    'response_body' => $body !== null && $body !== '' ? substr($body, 0, 500) : '',
                    'raw_json_preview' => is_array($raw) ? json_encode(array_slice($raw, 0, 1)) : substr($body ?? '', 0, 300),
                ]);

                if (! $response->successful()) {
                    Log::debug('Amazon structured API failed: non-success status', ['status' => $status, 'attempt' => $attempt]);
                    return null;
                }

                if (! is_array($raw)) {
                    Log::debug('Amazon structured API: response is not valid JSON', ['body_preview' => substr($body ?? '', 0, 300), 'attempt' => $attempt]);
                    return null;
                }

                $originalRaw = $raw;
                $raw = $this->unwrapAmazonStructuredResponse($raw);
                $normalized = $this->normalizeStructuredResult($raw, $url, 'amazon', 'amazon_structured_api');
                $normalized['scraperapi_raw'] = $originalRaw;
                $normalized['extraction_source'] = 'amazon_structured_api';
                $normalized['fetch_source'] = 'scraperapi';
                $normalized['html_strategy'] = 'structured_api';
                $normalized['blocked_or_captcha'] = false;

                Log::debug('Amazon structured normalized result', [
                    'normalized' => [
                        'name' => $normalized['name'] ?? '',
                        'price' => $normalized['price'] ?? 0,
                        'image_url' => isset($normalized['image_url']) ? 'set' : 'null',
                        'extraction_source' => $normalized['extraction_source'] ?? '',
                    ],
                ]);
                return $normalized;
            } catch (\Throwable $e) {
                $isTimeout = str_contains(strtolower($e->getMessage()), 'timed out') || str_contains(strtolower($e->getMessage()), 'timeout');
                Log::debug('Amazon structured API exception', [
                    'asin' => $asin,
                    'tld' => $tld,
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                    'url' => $url,
                    'timeout' => $isTimeout,
                ]);
                if ($attempt === $maxAttempts) {
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * Unwrap ScraperAPI response if it is nested under 'product' or 'data'.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function unwrapAmazonStructuredResponse(array $raw): array
    {
        if (isset($raw['product']) && is_array($raw['product'])) {
            return $raw['product'];
        }
        if (isset($raw['data']) && is_array($raw['data'])) {
            return $raw['data'];
        }
        return $raw;
    }

    /**
     * Try eBay structured product API. Returns normalized + scraperapi_raw or null on failure.
     *
     * @return array<string, mixed>|null
     */
    public function extractEbayStructured(string $url): ?array
    {
        $productId = $this->extractEbayItemId($url);
        if ($productId === null) {
            return null;
        }

        $tld = $this->ebayTldFromUrl($url);
        $countryCode = $this->ebayCountryCodeFromTld($tld);
        $apiKey = config('services.product_import.scraperapi_key');
        if (empty($apiKey)) {
            return null;
        }

        $apiUrl = 'https://api.scraperapi.com/structured/ebay/product/v1?' . http_build_query([
            'api_key' => $apiKey,
            'product_id' => $productId,
            'country_code' => $countryCode,
            'tld' => $tld,
        ]);

        try {
            $response = Http::timeout(self::STRUCTURED_TIMEOUT)->get($apiUrl);
            if (! $response->successful()) {
                Log::debug('eBay structured API: non-success status', [
                    'product_id' => $productId,
                    'status'       => $response->status(),
                ]);

                return null;
            }
            $raw = $response->json();
            if (! is_array($raw)) {
                return null;
            }
            $originalRaw = $raw;
            $raw = $this->unwrapEbayStructuredResponse($raw);
            $normalized = $this->normalizeStructuredResult($raw, $url, 'ebay', 'ebay_structured_api');
            $normalized['scraperapi_raw'] = $originalRaw;

            return $normalized;
        } catch (\Throwable $e) {
            Log::debug('eBay structured API: exception', [
                'product_id' => $productId,
                'message'    => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Unwrap ScraperAPI eBay response if nested under 'product' or 'data'.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function unwrapEbayStructuredResponse(array $raw): array
    {
        if (isset($raw['product']) && is_array($raw['product'])) {
            return $raw['product'];
        }
        if (isset($raw['data']) && is_array($raw['data'])) {
            return $raw['data'];
        }

        return $raw;
    }

    /**
     * Try Walmart structured product API. Returns normalized + scraperapi_raw or null on failure.
     *
     * @return array<string, mixed>|null
     */
    public function extractWalmartStructured(string $url): ?array
    {
        $productId = $this->extractWalmartProductId($url);
        if ($productId === null) {
            return null;
        }

        $tld = $this->walmartTldFromUrl($url);
        $countryCode = $tld === 'ca' ? 'ca' : 'us';
        $apiKey = config('services.product_import.scraperapi_key');
        if (empty($apiKey)) {
            return null;
        }

        $apiUrl = 'https://api.scraperapi.com/structured/walmart/product/v1?' . http_build_query([
            'api_key' => $apiKey,
            'product_id' => $productId,
            'country_code' => $countryCode,
            'tld' => $tld,
        ]);

        try {
            Log::debug('Walmart structured: request', ['product_id' => $productId, 'tld' => $tld]);
            $response = Http::timeout(self::STRUCTURED_TIMEOUT)->get($apiUrl);
            if (! $response->successful()) {
                Log::debug('Walmart structured: non-success', ['status' => $response->status()]);

                return null;
            }
            $raw = $response->json();
            if (! is_array($raw)) {
                return null;
            }
            $originalRaw = $raw;
            $raw = $this->unwrapWalmartStructuredResponse($raw);
            $normalized = $this->normalizeStructuredResult($raw, $url, 'walmart', 'walmart_structured_api');
            $normalized['scraperapi_raw'] = $originalRaw;

            return $normalized;
        } catch (\Throwable $e) {
            Log::debug('Walmart structured: exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function unwrapWalmartStructuredResponse(array $raw): array
    {
        if (isset($raw['product']) && is_array($raw['product'])) {
            return $raw['product'];
        }
        if (isset($raw['data']) && is_array($raw['data'])) {
            return $raw['data'];
        }

        return $raw;
    }

    /**
     * AliExpress: ScraperAPI standard scrape with render=true; return extraction result + scraperapi_raw metadata.
     * The HTML is fed to the existing pipeline by the caller; this method returns normalized + raw metadata.
     *
     * @return array{normalized: array<string, mixed>, html: string, scraperapi_raw: array<string, mixed>}|null
     */
    public function extractAliExpressRendered(string $url): ?array
    {
        $apiKey = config('services.product_import.scraperapi_key');
        if (empty($apiKey)) {
            return null;
        }

        $params = [
            'api_key' => $apiKey,
            'url' => $url,
            'render' => 'true',
        ];
        $apiUrl = 'https://api.scraperapi.com/?' . http_build_query($params);

        try {
            $response = Http::timeout(self::STRUCTURED_TIMEOUT)->get($apiUrl);
            if (! $response->successful()) {
                return null;
            }
            $body = $response->body();
            $scraperapiRaw = [
                'request_url' => $apiUrl,
                'render' => true,
                'response_content_type' => $response->header('Content-Type'),
                'response_length' => strlen($body ?? ''),
            ];
            return [
                'normalized' => [],
                'html' => $body ?? '',
                'scraperapi_raw' => $scraperapiRaw,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Normalize ScraperAPI structured response into our standard format and attach full raw JSON.
     *
     * @param  array<string, mixed>  $rawResponse
     * @return array<string, mixed>
     */
    public function normalizeStructuredResult(array $rawResponse, string $url, string $storeKey, string $extractionSource): array
    {
        $storeKey = strtolower($storeKey);
        $name = 'Product';
        $price = 0.0;
        $currency = 'USD';
        $imageUrl = null;

        if ($storeKey === 'amazon') {
            $name = trim((string) ($rawResponse['name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($rawResponse['title'] ?? 'Product'));
            }
            if ($name === '') {
                $name = 'Product';
            }
            if (isset($rawResponse['high_res_images'][0]) && is_string($rawResponse['high_res_images'][0])) {
                $imageUrl = $rawResponse['high_res_images'][0];
            }
            if ($imageUrl === null && isset($rawResponse['images'][0]) && is_string($rawResponse['images'][0])) {
                $imageUrl = $rawResponse['images'][0];
            }
            if (isset($rawResponse['pricing']) && (is_string($rawResponse['pricing']) || is_numeric($rawResponse['pricing']))) {
                $pricingVal = $rawResponse['pricing'];
                if (is_numeric($pricingVal)) {
                    $price = (float) $pricingVal;
                } elseif (is_string($pricingVal) && preg_match('/[\d,]+\.?\d*/', $pricingVal, $m)) {
                    $price = (float) str_replace(',', '', $m[0]);
                }
            }
            if ($price === 0.0 && isset($rawResponse['price']) && (is_float($rawResponse['price']) || is_numeric($rawResponse['price']))) {
                $price = (float) $rawResponse['price'];
            }
            if ($price === 0.0 && isset($rawResponse['product_information']) && is_array($rawResponse['product_information'])) {
                $info = $rawResponse['product_information'];
                if (isset($info['price']) && (is_numeric($info['price']) || is_string($info['price']))) {
                    $price = (float) (is_string($info['price']) ? str_replace(',', '', $info['price']) : $info['price']);
                }
            }
            if (isset($rawResponse['currency']) && is_string($rawResponse['currency'])) {
                $currency = $rawResponse['currency'];
            }
        }

        if ($storeKey === 'ebay') {
            $name = (string) ($rawResponse['title'] ?? $rawResponse['name'] ?? 'Product');
            $name = trim($name);
            if ($name === '') {
                $name = 'Product';
            }
            if (isset($rawResponse['price']) && is_array($rawResponse['price'])) {
                if (isset($rawResponse['price']['value']) && is_numeric($rawResponse['price']['value'])) {
                    $price = (float) $rawResponse['price']['value'];
                }
                if (isset($rawResponse['price']['currency']) && is_string($rawResponse['price']['currency'])) {
                    $currency = $rawResponse['price']['currency'];
                }
            }
            if ($price === 0.0 && isset($rawResponse['price']) && is_numeric($rawResponse['price'])) {
                $price = (float) $rawResponse['price'];
            }
            if (isset($rawResponse['image']) && is_string($rawResponse['image'])) {
                $imageUrl = $rawResponse['image'];
            }
            if ($imageUrl === null && isset($rawResponse['images'][0]) && is_string($rawResponse['images'][0])) {
                $imageUrl = $rawResponse['images'][0];
            }
        }

        if ($storeKey === 'walmart') {
            $name = (string) ($rawResponse['name'] ?? $rawResponse['title'] ?? 'Product');
            $name = trim($name);
            if ($name === '') {
                $name = 'Product';
            }
            if (isset($rawResponse['current_price']) && is_numeric($rawResponse['current_price'])) {
                $price = (float) $rawResponse['current_price'];
            }
            if ($price === 0.0 && isset($rawResponse['price']) && is_numeric($rawResponse['price'])) {
                $price = (float) $rawResponse['price'];
            }
            if (isset($rawResponse['currency']) && is_string($rawResponse['currency'])) {
                $currency = $rawResponse['currency'];
            }
            if (isset($rawResponse['main_image']) && is_string($rawResponse['main_image'])) {
                $imageUrl = $rawResponse['main_image'];
            }
            if ($imageUrl === null && isset($rawResponse['images'][0]) && is_string($rawResponse['images'][0])) {
                $imageUrl = $rawResponse['images'][0];
            }
        }

        $storeName = self::STORE_DISPLAY_NAMES[$storeKey] ?? ucfirst($storeKey);
        $country = self::COUNTRY_BY_STORE[$storeKey] ?? 'Unknown';

        if ($imageUrl !== null && ! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $imageUrl = null;
        }

        // --- Weight & Dimensions ---
        $weight = null;
        $weightUnit = null;
        $dimensions = null;
        $measurementSources = [
            'weight' => ['key' => null, 'raw' => null],
            'dimensions' => ['key' => null, 'raw' => null],
        ];
        $measurementsSourceString = null;
        $hasExactMeasurements = false;

        if ($storeKey === 'amazon') {
            $info = is_array($rawResponse['product_information'] ?? null) ? $rawResponse['product_information'] : [];

            $meas = $this->extractAmazonMeasurements($rawResponse, $info);
            if (is_string($meas['weight_raw'] ?? null) && trim((string) $meas['weight_raw']) !== '') {
                [$weight, $weightUnit] = $this->parseWeightString((string) $meas['weight_raw']);
                if ($weight !== null && $weightUnit !== null) {
                    $measurementSources['weight'] = ['key' => $meas['weight_key'] ?? null, 'raw' => (string) $meas['weight_raw']];
                }
            }
            if (is_string($meas['dimensions_raw'] ?? null) && trim((string) $meas['dimensions_raw']) !== '') {
                $dimensions = $this->parseDimensionString((string) $meas['dimensions_raw']);
                if ($dimensions !== null) {
                    $measurementSources['dimensions'] = ['key' => $meas['dimensions_key'] ?? null, 'raw' => (string) $meas['dimensions_raw']];
                }
            }
        }

        if ($storeKey === 'ebay') {
            $ebayMeas = $this->extractEbayItemSpecificsMeasurements($rawResponse);
            $weight = $ebayMeas['weight'];
            $weightUnit = $ebayMeas['weight_unit'];
            $dimensions = $ebayMeas['dimensions'];
            $measurementSources = $ebayMeas['measurement_sources'];
        }

        if ($storeKey === 'walmart') {
            $wm = $this->extractWalmartStructuredMeasurements($rawResponse);
            $weight = $wm['weight'];
            $weightUnit = $wm['weight_unit'];
            $dimensions = $wm['dimensions'];
            $measurementSources = $wm['measurement_sources'];
            $hasExactMeasurements = $wm['has_exact_measurements'];
            if ($wm['has_any_measurements']) {
                $measurementsSourceString = 'walmart_structured';
            }
        }

        if ($storeKey === 'ebay') {
            $hasExactMeasurements = $weight !== null
                && $weightUnit !== null
                && $dimensions !== null
                && ($measurementSources['weight']['key'] ?? null) !== null
                && ($measurementSources['dimensions']['key'] ?? null) !== null;
        } elseif ($storeKey === 'walmart') {
            // $hasExactMeasurements already set from extractWalmartStructuredMeasurements
        } else {
            $hasExactMeasurements = $weight !== null
                && $dimensions !== null
                && ($measurementSources['weight']['key'] ?? null) !== null
                && ($measurementSources['dimensions']['key'] ?? null) !== null;
        }

        $measurementsSourceString = $measurementsSourceString ?? null;
        if ($hasExactMeasurements && $storeKey === 'amazon') {
            $wk = (string) ($measurementSources['weight']['key'] ?? '');
            $dk = (string) ($measurementSources['dimensions']['key'] ?? '');
            $measurementsSourceString = trim('amazon_structured:' . $wk . '|' . $dk);
        }
        if ($hasExactMeasurements && $storeKey === 'ebay') {
            $wk = (string) ($measurementSources['weight']['key'] ?? '');
            $dk = (string) ($measurementSources['dimensions']['key'] ?? '');
            $measurementsSourceString = trim('ebay_structured:' . $wk . '|' . $dk);
            Log::debug('eBay structured: normalized measurements', [
                'weight'        => $weight,
                'weight_unit'   => $weightUnit,
                'dimensions'    => $dimensions,
                'has_exact'     => true,
                'source_string' => $measurementsSourceString,
            ]);
        }
        if ($storeKey === 'walmart' && ($measurementsSourceString ?? null) === 'walmart_structured') {
            Log::debug('Walmart structured: normalized measurements', [
                'matched_labels' => array_column($measurementSources['walmart_matched_labels'] ?? [], 'label'),
                'weight'         => $weight,
                'dimensions'     => $dimensions,
                'has_exact'      => $hasExactMeasurements,
            ]);
        }

        return [
            'name' => $name,
            'price' => $price,
            'currency' => $currency,
            'image_url' => $imageUrl,
            'store_key' => $storeKey,
            'store_name' => $storeName,
            'country' => $country,
            'canonical_url' => $url,
            'extraction_source' => $extractionSource,
            'fetch_source' => 'scraperapi',
            'html_strategy' => 'structured_api',
            'blocked_or_captcha' => false,
            // Weight (flat — consumed by ProductToShippingInputMapper)
            'weight' => $weight,
            'weight_unit' => $weightUnit,
            // Normalized measurement output (explicit; do not guess)
            'weight_value' => $weight,
            'dimensions_length' => $dimensions['length'] ?? null,
            'dimensions_width' => $dimensions['width'] ?? null,
            'dimensions_height' => $dimensions['height'] ?? null,
            'dimensions_unit' => $dimensions['unit'] ?? null,
            'has_exact_measurements' => $hasExactMeasurements,
            'measurements_source' => $measurementsSourceString,
            'measurements_source_fields' => $measurementSources,
            // Dimensions flat fields — consumed by ProductToShippingInputMapper
            'length' => $dimensions['length'] ?? null,
            'width' => $dimensions['width'] ?? null,
            'height' => $dimensions['height'] ?? null,
            'dimension_unit' => $dimensions['unit'] ?? null,
            // Nested dimensions object — for API response display
            'dimensions' => $dimensions,
            'scraperapi_raw' => $rawResponse,
        ];
    }

    /**
     * Walmart: merge specifications + product_highlights, then extract weight and dimensions (multiple shapes).
     *
     * @return array{
     *   weight: float|null,
     *   weight_unit: string|null,
     *   dimensions: array<string, mixed>|null,
     *   has_exact_measurements: bool,
     *   has_any_measurements: bool,
     *   measurement_sources: array<string, mixed>
     * }
     */
    private function extractWalmartStructuredMeasurements(array $rawResponse): array
    {
        $map = $this->buildWalmartNormalizedLabelMap($rawResponse);
        Log::debug('Walmart structured: merged label keys', ['labels' => array_keys($map)]);

        $matched = [];
        $weight = null;
        $weightUnit = null;
        $weightKey = null;
        $weightRaw = null;

        foreach (['item weight', 'weight'] as $want) {
            if (! isset($map[$want])) {
                continue;
            }
            $entry = $map[$want];
            [$w, $wu] = $this->parseWeightString($entry['value']);
            if ($w !== null && $wu !== null) {
                $weight = $w;
                $weightUnit = $wu;
                $weightKey = $entry['label'];
                $weightRaw = $entry['value'];
                $matched[] = ['label' => $entry['label'], 'raw' => $entry['value']];
                break;
            }
        }

        $dimensions = null;
        $dimsKey = null;
        $dimsRaw = null;
        $hasExact = false;

        foreach (['assembled product dimensions', 'product dimensions', 'dimensions'] as $want) {
            if (! isset($map[$want])) {
                continue;
            }
            $entry = $map[$want];
            $parsed = $this->parseDimensionString($entry['value']);
            if ($parsed !== null) {
                $dimensions = $parsed;
                $dimsKey = $entry['label'];
                $dimsRaw = $entry['value'];
                $matched[] = ['label' => $entry['label'], 'raw' => $entry['value']];
                $hasExact = true;
                break;
            }
        }

        if ($dimensions === null) {
            $h = $this->pickWalmartSplitDimension($map, ['assembled product height', 'height']);
            $wDim = $this->pickWalmartSplitDimension($map, ['assembled product width', 'width']);
            $len = $this->pickWalmartSplitDimension($map, ['assembled product length', 'length']);
            if ($len === null) {
                $len = $this->pickWalmartSplitDimension($map, ['assembled product depth', 'depth']);
            }

            if ($h !== null && $wDim === null && $len === null) {
                $dimensions = [
                    'length' => null,
                    'width' => null,
                    'height' => $h['value'],
                    'unit' => $h['unit'],
                ];
                $dimsKey = $h['label'];
                $dimsRaw = $h['raw'];
                $matched[] = ['label' => $h['label'], 'raw' => $h['raw']];
                $hasExact = false;
            } elseif ($h !== null || $wDim !== null || $len !== null) {
                $lv = $len !== null ? $len['value'] : null;
                $wv = $wDim !== null ? $wDim['value'] : null;
                $hv = $h !== null ? $h['value'] : null;
                $uL = $len !== null ? $len['unit'] : null;
                $uW = $wDim !== null ? $wDim['unit'] : null;
                $uH = $h !== null ? $h['unit'] : null;

                $dimensions = [
                    'length' => $lv,
                    'width' => $wv,
                    'height' => $hv,
                    'unit' => null,
                ];

                $parts = [];
                foreach ([$len, $wDim, $h] as $part) {
                    if ($part !== null) {
                        $parts[] = ['label' => $part['label'], 'raw' => $part['raw']];
                    }
                }
                foreach ($parts as $p) {
                    $matched[] = ['label' => $p['label'], 'raw' => $p['raw']];
                }
                if ($parts !== []) {
                    $dimsKey = implode('|', array_column($parts, 'label'));
                    $dimsRaw = implode('; ', array_map(static fn ($p) => $p['label'] . ': ' . $p['raw'], $parts));
                }

                if ($lv !== null && $wv !== null && $hv !== null && $uL !== null && $uL === $uW && $uL === $uH) {
                    $dimensions['unit'] = $uL;
                    $hasExact = true;
                } else {
                    $uniq = array_unique(array_values(array_filter([$uL, $uW, $uH], static fn ($x) => $x !== null)));
                    if (count($uniq) === 1) {
                        $dimensions['unit'] = $uniq[0];
                    } elseif ($h !== null) {
                        $dimensions['unit'] = $h['unit'];
                    } elseif ($wDim !== null) {
                        $dimensions['unit'] = $wDim['unit'];
                    } elseif ($len !== null) {
                        $dimensions['unit'] = $len['unit'];
                    }
                    $hasExact = false;
                }
            }
        }

        $hasAny = $weight !== null
            || ($dimensions !== null && (
                ($dimensions['length'] ?? null) !== null
                || ($dimensions['width'] ?? null) !== null
                || ($dimensions['height'] ?? null) !== null
            ));

        $measurementSources = [
            'weight' => $weightKey !== null ? ['key' => $weightKey, 'raw' => (string) $weightRaw] : ['key' => null, 'raw' => null],
            'dimensions' => ['key' => $dimsKey, 'raw' => $dimsRaw],
            'walmart_matched_labels' => $matched,
        ];

        return [
            'weight' => $weight,
            'weight_unit' => $weightUnit,
            'dimensions' => $dimensions,
            'has_exact_measurements' => $hasExact,
            'has_any_measurements' => $hasAny,
            'measurement_sources' => $measurementSources,
        ];
    }

    /**
     * @return array<string, array{label: string, value: string}>
     */
    private function buildWalmartNormalizedLabelMap(array $rawResponse): array
    {
        $map = [];
        $lists = [
            is_array($rawResponse['specifications'] ?? null) ? $rawResponse['specifications'] : [],
            is_array($rawResponse['product_highlights'] ?? null) ? $rawResponse['product_highlights'] : [],
        ];
        foreach ($lists as $list) {
            foreach ($list as $spec) {
                if (! is_array($spec)) {
                    continue;
                }
                $labelRaw = (string) ($spec['name'] ?? $spec['label'] ?? '');
                $value = trim((string) ($spec['value'] ?? ''));
                if ($value === '') {
                    continue;
                }
                $norm = $this->normalizeEbayItemSpecificLabel($labelRaw);
                if ($norm === '') {
                    continue;
                }
                $map[$norm] = ['label' => $labelRaw, 'value' => $value];
            }
        }

        return $map;
    }

    /**
     * @param  array<string, array{label: string, value: string}>  $map
     * @param  array<int, string>  $normLabels
     * @return array{value: float, unit: string, label: string, raw: string}|null
     */
    private function pickWalmartSplitDimension(array $map, array $normLabels): ?array
    {
        foreach ($normLabels as $want) {
            if (! isset($map[$want])) {
                continue;
            }
            $parsed = $this->parseEbaySingleDimensionValue($map[$want]['value']);
            if ($parsed !== null) {
                return [
                    'value' => $parsed['value'],
                    'unit' => $parsed['unit'],
                    'label' => $map[$want]['label'],
                    'raw' => $map[$want]['value'],
                ];
            }
        }

        return null;
    }

    /**
     * Parse eBay structured item_specifics for weight and dimensions (case-insensitive labels, trimmed).
     *
     * @param  array<string, mixed>  $rawResponse
     * @return array{
     *   weight: float|null,
     *   weight_unit: string|null,
     *   dimensions: array{length: float, width: float, height: float, unit: string}|null,
     *   measurement_sources: array<string, mixed>
     * }
     */
    private function extractEbayItemSpecificsMeasurements(array $rawResponse): array
    {
        $measurementSources = [
            'weight' => ['key' => null, 'raw' => null],
            'dimensions' => ['key' => null, 'raw' => null],
            'ebay_matched_labels' => [],
        ];

        $specs = is_array($rawResponse['item_specifics'] ?? null) ? $rawResponse['item_specifics'] : [];
        $rows = [];
        foreach ($specs as $spec) {
            if (! is_array($spec)) {
                continue;
            }
            $labelRaw = (string) ($spec['label'] ?? $spec['name'] ?? '');
            $value = trim((string) ($spec['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            $norm = $this->normalizeEbayItemSpecificLabel($labelRaw);
            if ($norm === '') {
                continue;
            }
            $rows[] = ['norm' => $norm, 'label' => $labelRaw, 'value' => $value];
        }

        $matchedForLog = [];

        $weight = null;
        $weightUnit = null;
        foreach (['item weight', 'net weight', 'weight', 'shipping weight'] as $want) {
            foreach ($rows as $row) {
                if ($row['norm'] !== $want) {
                    continue;
                }
                [$w, $wu] = $this->parseWeightString($row['value']);
                if ($w !== null && $wu !== null) {
                    $weight = $w;
                    $weightUnit = $wu;
                    $measurementSources['weight'] = ['key' => $row['label'], 'raw' => $row['value']];
                    $matchedForLog[] = ['label' => $row['label'], 'raw' => $row['value']];
                    break 2;
                }
            }
        }

        $dimensions = null;

        foreach (['item dimensions', 'product dimensions', 'dimensions'] as $want) {
            foreach ($rows as $row) {
                if ($row['norm'] !== $want) {
                    continue;
                }
                $parsed = $this->parseDimensionString($row['value']);
                if ($parsed !== null) {
                    $dimensions = $parsed;
                    $measurementSources['dimensions'] = ['key' => $row['label'], 'raw' => $row['value']];
                    $matchedForLog[] = ['label' => $row['label'], 'raw' => $row['value']];
                    break 2;
                }
            }
        }

        if ($dimensions === null) {
            $h = $this->pickEbaySingleDimension($rows, ['item height', 'height']);
            $wDim = $this->pickEbaySingleDimension($rows, ['item width', 'width']);
            $len = $this->pickEbaySingleDimension($rows, ['item length', 'length']);
            if ($len === null) {
                $len = $this->pickEbaySingleDimension($rows, ['item depth', 'depth']);
            }
            if ($h !== null && $wDim !== null && $len !== null) {
                $units = [$h['unit'], $wDim['unit'], $len['unit']];
                if (count(array_unique($units)) === 1) {
                    $unit = $h['unit'];
                    $dimensions = [
                        'length' => $len['value'],
                        'width' => $wDim['value'],
                        'height' => $h['value'],
                        'unit' => $unit,
                    ];
                    $dimsKey = $h['label'] . '|' . $wDim['label'] . '|' . $len['label'];
                    $dimsRaw = sprintf(
                        '%s: %s; %s: %s; %s: %s',
                        $len['label'],
                        $len['raw'],
                        $wDim['label'],
                        $wDim['raw'],
                        $h['label'],
                        $h['raw']
                    );
                    $measurementSources['dimensions'] = ['key' => $dimsKey, 'raw' => $dimsRaw];
                    foreach ([$len, $wDim, $h] as $part) {
                        $matchedForLog[] = ['label' => $part['label'], 'raw' => $part['raw']];
                    }
                }
            }
        }

        $measurementSources['ebay_matched_labels'] = $matchedForLog;

        Log::debug('eBay structured: item_specifics measurement extraction', [
            'matched_labels' => array_column($matchedForLog, 'label'),
            'weight'         => $weight,
            'weight_unit'    => $weightUnit,
            'dimensions'     => $dimensions,
        ]);

        return [
            'weight' => $weight,
            'weight_unit' => $weightUnit,
            'dimensions' => $dimensions,
            'measurement_sources' => $measurementSources,
        ];
    }

    private function normalizeEbayItemSpecificLabel(string $label): string
    {
        $s = preg_replace('/\s+/u', ' ', trim($label));

        return strtolower($s);
    }

    /**
     * @param  array<int, array{norm: string, label: string, value: string}>  $rows
     * @param  array<int, string>  $normLabels
     * @return array{value: float, unit: string, label: string, raw: string}|null
     */
    private function pickEbaySingleDimension(array $rows, array $normLabels): ?array
    {
        foreach ($normLabels as $want) {
            foreach ($rows as $row) {
                if ($row['norm'] !== $want) {
                    continue;
                }
                $parsed = $this->parseEbaySingleDimensionValue($row['value']);
                if ($parsed !== null) {
                    return [
                        'value' => $parsed['value'],
                        'unit' => $parsed['unit'],
                        'label' => $row['label'],
                        'raw' => $row['value'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return array{value: float, unit: string}|null
     */
    private function parseEbaySingleDimensionValue(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $raw = rtrim($raw, '.');
        if (preg_match('/^([\d.,]+)\s*"\s*$/u', $raw, $m)) {
            $v = (float) str_replace(',', '', $m[1]);

            return $v > 0 ? ['value' => $v, 'unit' => 'in'] : null;
        }
        if (! preg_match('/^([\d.,]+)\s*(.+)$/u', $raw, $m)) {
            return null;
        }
        $v = (float) str_replace(',', '', $m[1]);
        if ($v <= 0) {
            return null;
        }
        $rest = strtolower(trim($m[2]));
        $rest = rtrim($rest, '.');
        $unit = match (true) {
            preg_match('/^(?:"|inches|inch|in)\b/u', $rest) === 1 => 'in',
            preg_match('/^(?:cm|centimeters?)\b/u', $rest) === 1 => 'cm',
            preg_match('/^(?:mm|millimeters?)\b/u', $rest) === 1 => 'mm',
            default => null,
        };
        if ($unit === null) {
            return null;
        }

        return ['value' => $v, 'unit' => $unit];
    }

    /**
     * Amazon structured responses may expose measurements under multiple equivalent keys.
     * We search explicit known keys first, then fall back to scanning for likely keys.
     *
     * @param  array<string, mixed>  $rawResponse
     * @param  array<string, mixed>  $productInformation
     * @return array{weight_key: string|null, weight_raw: string|null, dimensions_key: string|null, dimensions_raw: string|null}
     */
    private function extractAmazonMeasurements(array $rawResponse, array $productInformation): array
    {
        $candidatesWeight = [
            ['scope' => 'product_information', 'key' => 'Item Weight'],
            ['scope' => 'product_information', 'key' => 'item_weight'],
            ['scope' => 'product_information', 'key' => 'Item weight'],
            ['scope' => 'product_information', 'key' => 'item weight'],
            ['scope' => 'product_information', 'key' => 'item_weight_value'],
            ['scope' => 'product_information', 'key' => 'item_weight'],
            ['scope' => 'raw', 'key' => 'item_weight'],
            ['scope' => 'raw', 'key' => 'weight'],
        ];

        $candidatesDims = [
            ['scope' => 'product_information', 'key' => 'Product Dimensions'],
            ['scope' => 'product_information', 'key' => 'Package Dimensions'],
            ['scope' => 'product_information', 'key' => 'product_dimensions'],
            ['scope' => 'product_information', 'key' => 'package_dimensions'],
            ['scope' => 'product_information', 'key' => 'item_dimensions_d_x_w_x_h'],
            ['scope' => 'product_information', 'key' => 'item_dimensions_l_x_w_x_h'],
            ['scope' => 'product_information', 'key' => 'item_dimensions'],
            ['scope' => 'raw', 'key' => 'product_dimensions'],
            ['scope' => 'raw', 'key' => 'package_dimensions'],
            ['scope' => 'raw', 'key' => 'dimensions'],
        ];

        $weightKey = null;
        $weightRaw = null;
        foreach ($candidatesWeight as $c) {
            $v = $c['scope'] === 'product_information'
                ? ($productInformation[$c['key']] ?? null)
                : ($rawResponse[$c['key']] ?? null);
            if (is_string($v) && trim($v) !== '') {
                $parsed = $this->parseWeightString($v);
                if ($parsed[0] !== null && $parsed[1] !== null) {
                    $weightKey = $c['scope'] === 'product_information' ? ('product_information.' . $c['key']) : $c['key'];
                    $weightRaw = $v;
                    break;
                }
            }
        }

        if ($weightRaw === null) {
            foreach ($productInformation as $k => $v) {
                if (! is_string($v) || trim($v) === '') continue;
                $kNorm = strtolower(str_replace([' ', '-', '_'], '', (string) $k));
                if (! str_contains($kNorm, 'weight')) continue;
                $parsed = $this->parseWeightString($v);
                if ($parsed[0] !== null && $parsed[1] !== null) {
                    $weightKey = 'product_information.' . (string) $k;
                    $weightRaw = $v;
                    break;
                }
            }
        }

        $dimsKey = null;
        $dimsRaw = null;
        foreach ($candidatesDims as $c) {
            $v = $c['scope'] === 'product_information'
                ? ($productInformation[$c['key']] ?? null)
                : ($rawResponse[$c['key']] ?? null);
            if (is_string($v) && trim($v) !== '') {
                $parsed = $this->parseDimensionString($v);
                if ($parsed !== null) {
                    $dimsKey = $c['scope'] === 'product_information' ? ('product_information.' . $c['key']) : $c['key'];
                    $dimsRaw = $v;
                    break;
                }
            }
        }

        if ($dimsRaw === null) {
            foreach ($productInformation as $k => $v) {
                if (! is_string($v) || trim($v) === '') continue;
                $kNorm = strtolower(str_replace([' ', '-', '_'], '', (string) $k));
                if (! str_contains($kNorm, 'dimension')) continue;
                $parsed = $this->parseDimensionString($v);
                if ($parsed !== null) {
                    $dimsKey = 'product_information.' . (string) $k;
                    $dimsRaw = $v;
                    break;
                }
            }
        }

        return [
            'weight_key' => $weightKey,
            'weight_raw' => $weightRaw,
            'dimensions_key' => $dimsKey,
            'dimensions_raw' => $dimsRaw,
        ];
    }

    /**
     * Parse a weight string like "1.5 pounds", "680 grams", "2.3 kg", "5 lbs".
     * Returns [float $value, string $unit] or [null, null] on failure.
     *
     * @return array{float|null, string|null}
     */
    private function parseWeightString(string $raw): array
    {
        $raw = trim($raw);
        if (! preg_match('/^([\d.,]+)\s*([a-zA-Z]+)/u', $raw, $m)) {
            return [null, null];
        }
        $value = (float) str_replace(',', '', $m[1]);
        $unit  = strtolower(trim($m[2]));

        $unit = match (true) {
            in_array($unit, ['pound', 'pounds', 'lb', 'lbs'], true) => 'lb',
            in_array($unit, ['ounce', 'ounces', 'oz'], true)        => 'oz',
            in_array($unit, ['gram', 'grams', 'g'], true)           => 'g',
            in_array($unit, ['kilogram', 'kilograms', 'kg'], true)  => 'kg',
            default => $unit,
        };

        return $value > 0 ? [$value, $unit] : [null, null];
    }

    /**
     * Parse a dimension string like "10 x 5 x 3 inches" or "25.4 x 12.7 x 7.6 cm".
     * Returns ['length'=>float,'width'=>float,'height'=>float,'unit'=>string] or null.
     *
     * @return array{length: float, width: float, height: float, unit: string}|null
     */
    private function parseDimensionString(string $raw): ?array
    {
        $raw = trim($raw);

        // Format: "15"D x 15"W x 14"H" — inches symbol after each number with D/W/H labels
        if (preg_match('/([\d.,]+)"[DdLl]\s*[xX×]\s*([\d.,]+)"[Ww]\s*[xX×]\s*([\d.,]+)"[Hh]/u', $raw, $m)) {
            $l = (float) str_replace(',', '', $m[1]);
            $w = (float) str_replace(',', '', $m[2]);
            $h = (float) str_replace(',', '', $m[3]);
            if ($l > 0 && $w > 0 && $h > 0) {
                return ['length' => $l, 'width' => $w, 'height' => $h, 'unit' => 'in'];
            }
        }

        // Match: number x number x number [unit]
        if (! preg_match('/^([\d.,]+)\s*[xX×]\s*([\d.,]+)\s*[xX×]\s*([\d.,]+)\s*([a-zA-Z"]*)/u', $raw, $m)) {
            return null;
        }
        $l = (float) str_replace(',', '', $m[1]);
        $w = (float) str_replace(',', '', $m[2]);
        $h = (float) str_replace(',', '', $m[3]);
        if ($l <= 0 || $w <= 0 || $h <= 0) {
            return null;
        }
        $unit = strtolower(trim($m[4]));
        $unit = match (true) {
            in_array($unit, ['inch', 'inches', 'in', '"'], true) => 'in',
            in_array($unit, ['centimeter', 'centimeters', 'cm'], true) => 'cm',
            in_array($unit, ['millimeter', 'millimeters', 'mm'], true) => 'mm',
            $unit === '' => '', // DO NOT GUESS measurements unit
            default => $unit,
        };

        // No unit explicitly found → do not guess.
        if ($unit === '') {
            return null;
        }

        return ['length' => $l, 'width' => $w, 'height' => $h, 'unit' => $unit];
    }

    private function amazonTldFromUrl(string $url): string
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        if (Str::contains($host, 'amazon.co.uk')) {
            return 'co.uk';
        }
        if (Str::contains($host, 'amazon.ca')) {
            return 'ca';
        }
        if (Str::contains($host, 'amazon.de')) {
            return 'de';
        }
        if (Str::contains($host, 'amazon.fr')) {
            return 'fr';
        }
        if (Str::contains($host, 'amazon.it')) {
            return 'it';
        }
        if (Str::contains($host, 'amazon.es')) {
            return 'es';
        }
        if (Str::contains($host, 'amazon.co.jp')) {
            return 'co.jp';
        }
        if (Str::contains($host, 'amazon.in')) {
            return 'in';
        }
        if (Str::contains($host, 'amazon.com.au')) {
            return 'com.au';
        }
        if (Str::contains($host, 'amazon.')) {
            $parts = explode('.', $host);
            return implode('.', array_slice($parts, 1));
        }
        return 'com';
    }

    private function amazonCountryCodeFromTld(string $tld): string
    {
        $map = [
            'com' => 'us',
            'co.uk' => 'uk',
            'ca' => 'ca',
            'de' => 'de',
            'fr' => 'fr',
            'it' => 'it',
            'es' => 'es',
            'ae' => 'ae',
            'co.jp' => 'jp',
            'in' => 'in',
            'com.au' => 'au',
        ];
        return $map[$tld] ?? 'us';
    }

    private function ebayTldFromUrl(string $url): string
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        if (Str::contains($host, 'ebay.co.uk')) {
            return 'co.uk';
        }
        if (Str::contains($host, 'ebay.de')) {
            return 'de';
        }
        if (Str::contains($host, 'ebay.ca')) {
            return 'ca';
        }
        if (Str::contains($host, 'ebay.fr')) {
            return 'fr';
        }
        if (Str::contains($host, 'ebay.')) {
            $parts = explode('.', $host);
            return implode('.', array_slice($parts, 1));
        }
        return 'com';
    }

    private function ebayCountryCodeFromTld(string $tld): string
    {
        $map = ['com' => 'us', 'co.uk' => 'uk', 'de' => 'de', 'ca' => 'ca', 'fr' => 'fr', 'com.au' => 'au'];
        return $map[$tld] ?? 'us';
    }

    private function walmartTldFromUrl(string $url): string
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        if (Str::contains($host, 'walmart.ca')) {
            return 'ca';
        }
        return 'com';
    }
}
