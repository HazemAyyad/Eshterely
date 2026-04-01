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
     * Extract Walmart product ID from URL (e.g. /ip/5253396052 or /ip/Product-Name/123456).
     */
    public function extractWalmartProductId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null || $path === '') {
            return null;
        }
        if (preg_match('#/ip/(?:[^/]+/)?(\d+)#', $path, $m)) {
            return $m[1];
        }
        if (preg_match('#/ip/([A-Z0-9]+)#i', $path, $m)) {
            return $m[1];
        }
        return null;
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

        $apiUrl = 'https://api.scraperapi.com/structured/ebay/product?' . http_build_query([
            'api_key' => $apiKey,
            'product_id' => $productId,
            'country_code' => $countryCode,
            'tld' => $tld,
        ]);

        try {
            $response = Http::timeout(self::STRUCTURED_TIMEOUT)->get($apiUrl);
            if (! $response->successful()) {
                return null;
            }
            $raw = $response->json();
            if (! is_array($raw)) {
                return null;
            }
            return $this->normalizeStructuredResult($raw, $url, 'ebay', 'ebay_structured_api');
        } catch (\Throwable) {
            return null;
        }
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

        $apiUrl = 'https://api.scraperapi.com/structured/walmart/product?' . http_build_query([
            'api_key' => $apiKey,
            'product_id' => $productId,
            'country_code' => $countryCode,
            'tld' => $tld,
        ]);

        try {
            $response = Http::timeout(self::STRUCTURED_TIMEOUT)->get($apiUrl);
            if (! $response->successful()) {
                return null;
            }
            $raw = $response->json();
            if (! is_array($raw)) {
                return null;
            }
            return $this->normalizeStructuredResult($raw, $url, 'walmart', 'walmart_structured_api');
        } catch (\Throwable) {
            return null;
        }
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

        if ($storeKey === 'amazon') {
            $info = is_array($rawResponse['product_information'] ?? null) ? $rawResponse['product_information'] : [];

            // Weight: "Item Weight" key, e.g. "1.5 pounds" or "680 grams"
            $weightRaw = $info['Item Weight'] ?? $info['item_weight'] ?? $rawResponse['weight'] ?? null;
            if (is_string($weightRaw) && trim($weightRaw) !== '') {
                [$weight, $weightUnit] = $this->parseWeightString($weightRaw);
            }

            // Dimensions: try multiple key variants ScraperAPI may return.
            $dimRaw = $info['Package Dimensions']
                ?? $info['Product Dimensions']
                ?? $info['package_dimensions']
                ?? $info['product_dimensions']
                ?? $info['item_dimensions_d_x_w_x_h']
                ?? $info['item_dimensions_l_x_w_x_h']
                ?? $info['item_dimensions']
                ?? null;
            if (is_string($dimRaw) && trim($dimRaw) !== '') {
                $dimensions = $this->parseDimensionString($dimRaw);
            }
        }

        if ($storeKey === 'ebay') {
            // eBay item_specifics may contain "Weight", "Item Width", etc.
            $specs = is_array($rawResponse['item_specifics'] ?? null) ? $rawResponse['item_specifics'] : [];
            foreach ($specs as $spec) {
                if (! is_array($spec)) continue;
                $label = strtolower(trim((string) ($spec['label'] ?? $spec['name'] ?? '')));
                $value = trim((string) ($spec['value'] ?? ''));
                if ($value === '') continue;
                if (in_array($label, ['weight', 'item weight', 'net weight'], true)) {
                    [$weight, $weightUnit] = $this->parseWeightString($value);
                }
            }
        }

        if ($storeKey === 'walmart') {
            $specs = is_array($rawResponse['specifications'] ?? null) ? $rawResponse['specifications'] : [];
            foreach ($specs as $spec) {
                if (! is_array($spec)) continue;
                $label = strtolower(trim((string) ($spec['name'] ?? $spec['label'] ?? '')));
                $value = trim((string) ($spec['value'] ?? ''));
                if ($value === '') continue;
                if (str_contains($label, 'weight')) {
                    [$weight, $weightUnit] = $this->parseWeightString($value);
                }
                if (str_contains($label, 'dimension') || str_contains($label, 'size')) {
                    $parsed = $this->parseDimensionString($value);
                    if ($parsed !== null) $dimensions = $parsed;
                }
            }
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
