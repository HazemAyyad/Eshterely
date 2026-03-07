<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Uses ScraperAPI structured/rendered endpoints for supported stores.
 * Returns normalized product data + full raw ScraperAPI response in scraperapi_raw.
 */
class StructuredProductImportService
{
    private const STRUCTURED_TIMEOUT = 45;

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
     * Extract ASIN from Amazon URLs: /dp/{ASIN}, /gp/product/{ASIN}, /product/{ASIN}.
     */
    public function extractAmazonAsin(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null || $path === '') {
            return null;
        }
        if (preg_match('#/(?:dp|gp/product|product)/([A-Z0-9]{10})#i', $path, $m)) {
            return strtoupper($m[1]);
        }
        return null;
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
            return null;
        }

        $tld = $this->amazonTldFromUrl($url);
        $countryCode = $this->amazonCountryCodeFromTld($tld);
        $apiKey = config('services.product_import.scraperapi_key');
        if (empty($apiKey)) {
            return null;
        }

        $apiUrl = 'https://api.scraperapi.com/structured/amazon/product?' . http_build_query([
            'api_key' => $apiKey,
            'asin' => $asin,
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
            return $this->normalizeStructuredResult($raw, $url, 'amazon', 'amazon_structured_api');
        } catch (\Throwable) {
            return null;
        }
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
            $name = (string) ($rawResponse['name'] ?? 'Product');
            $name = trim($name);
            if ($name === '') {
                $name = 'Product';
            }
            if (isset($rawResponse['product_information']['asin'])) {
                $name = $name ?: 'Product';
            }
            if (isset($rawResponse['images'][0]) && is_string($rawResponse['images'][0])) {
                $imageUrl = $rawResponse['images'][0];
            }
            if (isset($rawResponse['price']) && (is_float($rawResponse['price']) || is_numeric($rawResponse['price']))) {
                $price = (float) $rawResponse['price'];
            }
            if ($price === 0.0 && isset($rawResponse['pricing']) && is_string($rawResponse['pricing'])) {
                if (preg_match('/[\d,]+\.?\d*/', $rawResponse['pricing'], $m)) {
                    $price = (float) str_replace(',', '', $m[0]);
                }
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
            'scraperapi_raw' => $rawResponse,
        ];
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
