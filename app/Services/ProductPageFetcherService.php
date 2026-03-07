<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Fetches HTML for product URL import.
 * For Amazon: can use rendered-fetch (e.g. ScraperAPI) when configured; otherwise direct HTTP.
 * For other stores: direct HTTP with improved headers.
 */
class ProductPageFetcherService
{
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
    ];

    /**
     * Fetch HTML for a product URL. For Amazon, may use rendered fetcher when configured.
     *
     * @return array{html: string, fetch_source: string, html_strategy: string, blocked_or_captcha: bool}
     */
    public function fetchHtml(string $url, string $storeKey): array
    {
        $storeKey = strtolower($storeKey);
        $isAmazon = $storeKey === 'amazon';

        if ($isAmazon && $this->shouldUseRenderedFetcher()) {
            $result = $this->fetchAmazonRendered($url);
            if ($result !== null) {
                return $result;
            }
        }

        $result = $this->fetchDirect($url);
        $result['fetch_source'] = $isAmazon && $this->shouldUseRenderedFetcher() ? 'fallback_http' : 'direct_http';
        $result['html_strategy'] = 'initial_html';

        return $result;
    }

    /**
     * Try to fetch Amazon page via configured rendered-fetch provider (e.g. ScraperAPI).
     * Returns null on failure so caller can fall back to direct HTTP.
     *
     * @return array{html: string, fetch_source: string, html_strategy: string, blocked_or_captcha: bool}|null
     */
    private function fetchAmazonRendered(string $url): ?array
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
