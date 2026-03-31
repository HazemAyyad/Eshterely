<?php

namespace App\Services\ProductImport\Providers;

use App\Models\ProductImportLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches fully rendered HTML via the external Node.js playwright-renderer service.
 *
 * This provider is a rendered-HTML source only — it does NOT extract product fields.
 * The returned HTML is passed directly into the existing extraction pipeline
 * (JSON-LD → Meta → DOM → OpenAI → Regex) unchanged.
 *
 * Position in attempt order: after free HTTP, before paid ScraperAPI.
 */
class PlaywrightProvider
{
    /**
     * Fetch rendered HTML for the given URL.
     *
     * @return array{html: string, fetch_source: string, html_strategy: string, blocked_or_captcha: bool, final_url: string|null}|null
     *         Returns null when the service is unavailable or the request fails.
     */
    public function render(string $url, string $storeKey, int $timeoutSeconds = 30): ?array
    {
        $startedAt = microtime(true);

        // Prefer ZenRows HTTP API when key is configured — provides US residential IPs
        // + JavaScript rendering + anti-bot bypass without needing the local Node service.
        $zenrowsKey = config('services.product_import.zenrows_api_key');
        if (! empty($zenrowsKey)) {
            return $this->renderViaZenRowsApi($url, $storeKey, $zenrowsKey, $timeoutSeconds, $startedAt);
        }

        // Fall back to the local Node playwright-renderer service.
        $serviceUrl = config('services.playwright.url');
        if (empty($serviceUrl)) {
            return null;
        }

        try {
            $response = Http::timeout($timeoutSeconds + 5)
                ->post(rtrim((string) $serviceUrl, '/') . '/render', [
                    'url'            => $url,
                    'timeoutSeconds' => $timeoutSeconds,
                ]);

            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

            if (! $response->successful()) {
                $this->logAttempt($url, $storeKey, false, $elapsedMs, 'HTTP ' . $response->status());
                return null;
            }

            $data = $response->json();

            if (! ($data['success'] ?? false) || empty($data['html'])) {
                $error = $data['error'] ?? 'empty response';
                $this->logAttempt($url, $storeKey, false, $elapsedMs, $error);
                return null;
            }

            $this->logAttempt($url, $storeKey, true, $elapsedMs, null);

            return [
                'html'              => (string) $data['html'],
                'fetch_source'      => 'playwright',
                'html_strategy'     => 'rendered_html',
                'blocked_or_captcha'=> false,
                'final_url'         => $data['finalUrl'] ?? null,
            ];

        } catch (\Throwable $e) {
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            Log::warning('PlaywrightProvider exception', ['url' => $url, 'error' => $e->getMessage()]);
            $this->logAttempt($url, $storeKey, false, $elapsedMs, $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch rendered HTML via ZenRows HTTP API with JS rendering + premium proxy.
     * This bypasses Amazon/Walmart bot detection by routing through US residential IPs.
     */
    private function renderViaZenRowsApi(
        string $url,
        string $storeKey,
        string $apiKey,
        int $timeoutSeconds,
        float $startedAt
    ): ?array {
        try {
            $apiUrl = 'https://api.zenrows.com/v1/?' . http_build_query([
                'apikey'        => $apiKey,
                'url'           => $url,
                'js_render'     => 'true',
                'premium_proxy' => 'true',
                'proxy_country' => 'us',
            ]);

            $response = Http::timeout($timeoutSeconds + 10)
                ->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml,*/*',
                ])
                ->get($apiUrl);

            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

            if (! $response->successful()) {
                $this->logAttempt($url, $storeKey, false, $elapsedMs, 'ZenRows HTTP ' . $response->status());
                return null;
            }

            $html = $response->body();
            if (empty(trim($html))) {
                $this->logAttempt($url, $storeKey, false, $elapsedMs, 'ZenRows empty response');
                return null;
            }

            $this->logAttempt($url, $storeKey, true, $elapsedMs, null);

            return [
                'html'               => $html,
                'fetch_source'       => 'zenrows',
                'html_strategy'      => 'rendered_html',
                'blocked_or_captcha' => false,
                'final_url'          => null,
            ];

        } catch (\Throwable $e) {
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            Log::warning('PlaywrightProvider ZenRows exception', ['url' => $url, 'error' => $e->getMessage()]);
            $this->logAttempt($url, $storeKey, false, $elapsedMs, $e->getMessage());
            return null;
        }
    }

    /**
     * Whether the playwright-renderer service is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty(config('services.playwright.url'));
    }

    /**
     * Write a compact log entry for this attempt.
     * Never stores full HTML — only metadata.
     */
    private function logAttempt(
        string $url,
        string $storeKey,
        bool $success,
        int $elapsedMs,
        ?string $errorMessage,
    ): void {
        try {
            ProductImportLog::create([
                'url'              => $url,
                'store_key'        => $storeKey,
                'provider'         => 'playwright',
                'attempt_index'    => 0,
                'success'          => $success,
                'partial_success'  => false,
                'used_paid_provider' => false,
                'error_message'    => $errorMessage,
                'response_snapshot'=> $success
                    ? ['fetch_source' => 'playwright', 'elapsed_ms' => $elapsedMs]
                    : null,
                'elapsed_ms'       => $elapsedMs,
            ]);
        } catch (\Throwable) {
            // Logging must never break the import flow.
        }
    }
}
