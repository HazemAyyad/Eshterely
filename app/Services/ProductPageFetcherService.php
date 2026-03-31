<?php

namespace App\Services;

use App\Models\ProductImportStoreSetting;
use App\Services\ProductImport\Providers\PlaywrightProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Fetches HTML for product URL import.
 *
 * Attempt order (free → paid):
 *   1. Direct HTTP          — always first, zero cost.
 *   2. Playwright renderer  — free rendered HTML via Node service; runs when direct HTTP is blocked
 *                             and store settings allow it (allow_playwright_fallback = true).
 *   3. ScraperAPI rendered  — paid fallback; only when Playwright is unavailable or also fails.
 */
class ProductPageFetcherService
{
    /** Store keys that use ScraperAPI for HTML fetch when configured. */
    private const SCRAPERAPI_STORES = ['amazon', 'ebay', 'walmart', 'aliexpress'];

    /**
     * Stores where direct HTTP always returns geo-restricted prices from non-US IPs.
     * When a rendered provider (ZenRows/Playwright) is configured, direct HTTP is skipped entirely.
     */
    private const SKIP_DIRECT_HTTP_STORES = ['amazon'];
    private const DIRECT_TIMEOUT = 15;

    private const RENDERED_TIMEOUT = 45;

    /**
     * Standard headers for direct HTTP fetches (browser-like).
     */
    private const DIRECT_HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.9',
        'Cache-Control' => 'no-cache',
        'Pragma' => 'no-cache',
        'Upgrade-Insecure-Requests' => '1',
    ];

    /**
     * Phrases that suggest a captcha / bot block page.
     */
    private const BLOCKED_PHRASES = [
        'captcha',
        'robot check',
        'automated access',
        'enter the characters you see below',
        'sorry, we just need to make sure you\'re not a robot',
        'access denied',
        'blocked',
        'not a robot',
        // Amazon geo-restriction: buy box is hidden; price won't be extractable from this IP.
        // ScraperAPI (US proxy) will serve the correct page with visible pricing.
        'this item cannot be shipped to your selected delivery location',
        'item cannot be shipped to the selected delivery location',
    ];

    /**
     * Fetch HTML for a product URL. For Amazon, eBay, Walmart, AliExpress uses ScraperAPI when configured.
     *
     * @return array{html: string, fetch_source: string, html_strategy: string, blocked_or_captcha: bool}
     */
    public function fetchHtml(string $url, string $storeKey): array
    {
        $storeKey = strtolower($storeKey);

        // For stores where direct HTTP always returns geo-restricted prices (e.g. Amazon),
        // skip it entirely when a rendered provider (ZenRows/Playwright) is configured.
        $skipDirect = in_array($storeKey, self::SKIP_DIRECT_HTTP_STORES, true)
            && $this->shouldUsePlaywright($storeKey);

        if (! $skipDirect) {
            // Try direct HTTP first (free).
            $result = $this->fetchDirect($url);
            $result['fetch_source'] = 'direct_http';
            $result['html_strategy'] = 'initial_html';

            // If direct fetch succeeded and page is not blocked, return immediately.
            if ($result['html'] !== '' && ! $result['blocked_or_captcha']) {
                return $result;
            }
        } else {
            // Placeholder so $result exists for the final fallback return.
            $result = ['html' => '', 'fetch_source' => 'direct_http', 'html_strategy' => 'initial_html', 'blocked_or_captcha' => true];
        }

        // Step 2: Playwright/ZenRows — rendered HTML (US IP, anti-bot bypass).
        if ($this->shouldUsePlaywright($storeKey)) {
            $playwrightResult = $this->fetchViaPlaywright($url, $storeKey);
            if ($playwrightResult !== null) {
                return $playwrightResult;
            }
        }

        // Step 3: ScraperAPI rendered — paid fallback (supported stores only).
        $canUseScraperApi = in_array($storeKey, self::SCRAPERAPI_STORES, true) && $this->shouldUseRenderedFetcher();
        if ($canUseScraperApi) {
            $scraperResult = $this->fetchViaScraperApiRendered($url);
            if ($scraperResult !== null) {
                return $scraperResult;
            }
        }

        // Return whatever direct fetch gave us (even if blocked/empty — caller handles it).
        return $result;
    }

    /**
     * Fetch via the Playwright renderer Node service.
     * Returns null when the service is unconfigured, unavailable, or returns empty HTML.
     *
     * @return array{html: string, fetch_source: string, html_strategy: string, blocked_or_captcha: bool}|null
     */
    private function fetchViaPlaywright(string $url, string $storeKey): ?array
    {
        $provider = app(PlaywrightProvider::class);
        if (! $provider->isConfigured()) {
            return null;
        }

        $timeoutSeconds = (int) config('services.playwright.timeout_seconds', 30);

        // Per-store timeout override when configured.
        $storeSetting = ProductImportStoreSetting::forStore($storeKey);
        if ($storeSetting && $storeSetting->playwright_timeout_seconds > 0) {
            $timeoutSeconds = $storeSetting->playwright_timeout_seconds;
        }

        $rendered = $provider->render($url, $storeKey, $timeoutSeconds);
        if ($rendered === null || $rendered['html'] === '') {
            return null;
        }

        return [
            'html'               => $rendered['html'],
            'fetch_source'       => $rendered['fetch_source'] ?? 'playwright',
            'html_strategy'      => 'rendered_html',
            'blocked_or_captcha' => false,
        ];
    }

    /**
     * Whether Playwright should be attempted for this store.
     * Requires: service configured + store setting allows it (or no setting found = allow by default).
     */
    private function shouldUsePlaywright(string $storeKey): bool
    {
        if (empty(config('services.playwright.url'))) {
            return false;
        }

        $setting = ProductImportStoreSetting::forStore($storeKey);
        // If no store setting exists, allow Playwright by default.
        if ($setting === null) {
            return true;
        }

        return (bool) $setting->allow_playwright_fallback;
    }

    /**
     * Try to fetch page via ScraperAPI with render=true (Amazon, eBay, Walmart, AliExpress).
     * Returns null on failure so caller can fall back to direct HTTP.
     *
     * @return array{html: string, fetch_source: string, html_strategy: string, blocked_or_captcha: bool}|null
     */
    private function fetchViaScraperApiRendered(string $url): ?array
    {
        $driver = config('services.product_import.rendered_fetcher', '');
        if ($driver === 'scraperapi') {
            return $this->fetchViaScraperApi($url);
        }

        return null;
    }

    /**
     * @return array{html: string, fetch_source: string, html_strategy: string, blocked_or_captcha: bool}|null
     */
    private function fetchViaScraperApi(string $url): ?array
    {
        $apiKey = config('services.product_import.scraperapi_key');
        if (empty($apiKey)) {
            return null;
        }

        try {
            $apiUrl = 'https://api.scraperapi.com/?' . http_build_query([
                'api_key' => $apiKey,
                'url' => $url,
                'render' => 'true',
            ]);

            $response = Http::timeout(self::RENDERED_TIMEOUT)
                ->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get($apiUrl);

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();
            if ($html === '' || ! $this->looksLikeHtml($response)) {
                return null;
            }

            $blocked = $this->detectBlockedOrCaptcha($html);

            return [
                'html' => $html,
                'fetch_source' => 'scraperapi',
                'html_strategy' => 'rendered_html',
                'blocked_or_captcha' => $blocked,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Direct HTTP fetch with improved headers and validation.
     *
     * @return array{html: string, fetch_source: string, html_strategy: string, blocked_or_captcha: bool}
     */
    private function fetchDirect(string $url): array
    {
        $html = '';
        $blocked = false;

        try {
            $response = Http::timeout(self::DIRECT_TIMEOUT)
                ->withHeaders(self::DIRECT_HEADERS)
                ->get($url);

            if ($response->successful() && $this->looksLikeHtml($response)) {
                $html = $response->body() ?? '';
            }
            if ($html !== '') {
                $blocked = $this->detectBlockedOrCaptcha($html);
            }
        } catch (\Throwable) {
            $html = '';
        }

        return [
            'html' => $html,
            'fetch_source' => 'direct_http',
            'html_strategy' => 'initial_html',
            'blocked_or_captcha' => $blocked,
        ];
    }

    private function shouldUseRenderedFetcher(): bool
    {
        $driver = config('services.product_import.rendered_fetcher', '');
        if ($driver !== 'scraperapi') {
            return false;
        }

        return ! empty(config('services.product_import.scraperapi_key'));
    }

    private function looksLikeHtml(\Illuminate\Http\Client\Response $response): bool
    {
        $body = $response->body();
        if ($body === null || $body === '') {
            return false;
        }

        $contentType = $response->header('Content-Type');
        if ($contentType !== null && stripos($contentType, 'text/html') === false && stripos($contentType, 'application/xhtml') === false) {
            return false;
        }

        return Str::startsWith(trim($body), '<') || stripos($body, '<html') !== false || stripos($body, '<!DOCTYPE') !== false;
    }

    /**
     * Detect likely captcha / bot block / access denied page.
     */
    public function detectBlockedOrCaptcha(string $html): bool
    {
        $lower = strtolower($html);
        foreach (self::BLOCKED_PHRASES as $phrase) {
            if (Str::contains($lower, $phrase)) {
                return true;
            }
        }

        return false;
    }
}
