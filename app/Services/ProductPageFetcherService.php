<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Fetches HTML for product URL import.
 *
 * Attempt order (free → paid):
 *   1. Direct HTTP          — always first, zero cost.
 *   2. ScraperAPI rendered  — paid fallback for supported stores (ebay, walmart, aliexpress).
 *
 * Special case — Amazon + ScraperAPI key configured:
 *   Returns a structured_api sentinel (empty HTML) immediately.
 *   ProductExtractionService will call the ScraperAPI structured endpoint directly,
 *   which returns clean US-priced product data including weight and dimensions.
 */
class ProductPageFetcherService
{
    /**
     * Store keys that use ScraperAPI rendered HTML as fallback when direct HTTP is blocked.
     * Amazon is excluded: it uses the structured endpoint instead.
     */
    private const SCRAPERAPI_RENDERED_STORES = ['ebay', 'walmart', 'aliexpress'];

    private const DIRECT_TIMEOUT = 15;

    private const RENDERED_TIMEOUT = 45;

    /**
     * Standard headers for direct HTTP fetches (browser-like).
     */
    private const DIRECT_HEADERS = [
        'User-Agent'               => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept'                   => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language'          => 'en-US,en;q=0.9',
        'Cache-Control'            => 'no-cache',
        'Pragma'                   => 'no-cache',
        'Upgrade-Insecure-Requests' => '1',
    ];

    /**
     * Phrases that suggest a captcha / bot-block page.
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
        'this item cannot be shipped to your selected delivery location',
        'item cannot be shipped to the selected delivery location',
    ];

    /**
     * Fetch HTML for a product URL.
     *
     * For Amazon when SCRAPERAPI_KEY is configured: returns a structured_api sentinel
     * (empty HTML) so the extraction layer calls the ScraperAPI structured endpoint directly.
     *
     * @return array{html: string, fetch_source: string, html_strategy: string, blocked_or_captcha: bool}
     */
    public function fetchHtml(string $url, string $storeKey): array
    {
        $storeKey = strtolower($storeKey);

        // Amazon + ScraperAPI key: structured endpoint is the primary provider.
        // Skip HTML fetch entirely — ProductExtractionService handles the structured call.
        if ($storeKey === 'amazon' && ! empty(config('services.product_import.scraperapi_key'))) {
            return [
                'html'               => '',
                'fetch_source'       => 'scraperapi_structured',
                'html_strategy'      => 'structured_api',
                'blocked_or_captcha' => false,
            ];
        }

        // Step 1: Try direct HTTP first (free).
        $result = $this->fetchDirect($url);
        $result['fetch_source'] = 'direct_http';
        $result['html_strategy'] = 'initial_html';

        if ($result['html'] !== '' && ! $result['blocked_or_captcha']) {
            return $result;
        }

        // Step 2: ScraperAPI rendered — paid fallback for supported stores.
        if (in_array($storeKey, self::SCRAPERAPI_RENDERED_STORES, true) && $this->shouldUseScraperApiRendered()) {
            $scraperResult = $this->fetchViaScraperApiRendered($url);
            if ($scraperResult !== null) {
                return $scraperResult;
            }
        }

        // Return whatever direct fetch gave us (caller handles empty/blocked).
        return $result;
    }

    /**
     * Direct HTTP fetch with browser-like headers.
     *
     * @return array{html: string, fetch_source: string, html_strategy: string, blocked_or_captcha: bool}
     */
    private function fetchDirect(string $url): array
    {
        $html    = '';
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
            'html'               => $html,
            'fetch_source'       => 'direct_http',
            'html_strategy'      => 'initial_html',
            'blocked_or_captcha' => $blocked,
        ];
    }

    /**
     * Fetch via ScraperAPI with render=true (paid rendered HTML).
     *
     * @return array{html: string, fetch_source: string, html_strategy: string, blocked_or_captcha: bool}|null
     */
    private function fetchViaScraperApiRendered(string $url): ?array
    {
        $apiKey = config('services.product_import.scraperapi_key');
        if (empty($apiKey)) {
            return null;
        }

        try {
            $apiUrl = 'https://api.scraperapi.com/?' . http_build_query([
                'api_key' => $apiKey,
                'url'     => $url,
                'render'  => 'true',
            ]);

            $response = Http::timeout(self::RENDERED_TIMEOUT)
                ->withHeaders([
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
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
                'html'               => $html,
                'fetch_source'       => 'scraperapi',
                'html_strategy'      => 'rendered_html',
                'blocked_or_captcha' => $blocked,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Whether ScraperAPI rendered fetcher is configured.
     */
    private function shouldUseScraperApiRendered(): bool
    {
        if (config('services.product_import.rendered_fetcher', '') !== 'scraperapi') {
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
        if ($contentType !== null
            && stripos($contentType, 'text/html') === false
            && stripos($contentType, 'application/xhtml') === false
        ) {
            return false;
        }

        return Str::startsWith(trim($body), '<')
            || stripos($body, '<html') !== false
            || stripos($body, '<!DOCTYPE') !== false;
    }

    /**
     * Detect likely captcha / bot-block / access-denied page.
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
