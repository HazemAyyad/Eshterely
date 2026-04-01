<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Services\ProductExtractionService;
use App\Services\ProductPageFetcherService;
use App\Services\ProductImport\StoreResolver;
use App\Services\StructuredProductImportService;
use App\Services\Shipping\FinalProductPricingService;
use App\Services\Shipping\ProductImportShippingQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ProductImportTesterController extends Controller
{
    public function index(): View
    {
        return view('admin.config.product-import.tester');
    }

    public function test(
        Request $request,
        ProductExtractionService $extractionService,
        ProductPageFetcherService $pageFetcher,
        ProductImportShippingQuoteService $shippingQuoteService,
        FinalProductPricingService $finalPricingService,
        StructuredProductImportService $structuredService,
    ): JsonResponse {
        $validated = $request->validate([
            'url'                  => 'required|url',
            'extraction_strategy'  => 'nullable|string|in:auto,jsonld,meta,dom,openai',
            'destination_country'  => 'nullable|string|max:10',
            'quantity'             => 'nullable|integer|min:1|max:100',
            'carrier'              => 'nullable|string|in:dhl,ups,fedex,auto',
        ]);

        $url      = $validated['url'];
        $strategy = $validated['extraction_strategy'] ?? 'auto';
        $storeKey = StoreResolver::resolve($url);

        // Store resolution debug
        $asin     = $storeKey === 'amazon' ? $structuredService->extractAmazonAsin($url) : null;
        $tld      = $storeKey === 'amazon' ? $structuredService->extractAmazonTld($url) : null;

        $timing           = ['started_at' => microtime(true)];
        $providerAttempts = [];

        try {
            // ── Fetch ────────────────────────────────────────────────────────────
            $timing['fetch_start'] = microtime(true);
            $fetchResult           = $pageFetcher->fetchHtml($url, $storeKey);
            $timing['fetch_ms']    = round((microtime(true) - $timing['fetch_start']) * 1000);

            $html        = $fetchResult['html'] ?? '';
            $htmlStrategy = $fetchResult['html_strategy'] ?? 'initial_html';

            $providerAttempts[] = [
                'provider' => $fetchResult['fetch_source'] ?? 'direct_http',
                'strategy' => $htmlStrategy,
                'success'  => $html !== '' || $htmlStrategy === 'structured_api',
                'note'     => $htmlStrategy === 'structured_api'
                    ? 'Delegated to ScraperAPI structured — no HTML fetch needed'
                    : ($html === '' ? 'Empty response' : 'HTML fetched (' . strlen($html) . ' bytes)'),
            ];

            // Allow empty HTML when structured_api is the provider (Amazon).
            if ($html === '' && $htmlStrategy !== 'structured_api') {
                return response()->json([
                    'ok'               => false,
                    'error'            => 'Could not fetch the URL — empty HTML returned.',
                    'store_resolution' => $this->buildStoreResolution($url, $storeKey, $asin, $tld),
                    'provider_attempts'=> $providerAttempts,
                    'meta'             => ['store_key' => $storeKey, 'fetch_source' => $fetchResult['fetch_source'] ?? null],
                ], 422);
            }

            $fetchMetadata = [
                'fetch_source'       => $fetchResult['fetch_source'] ?? 'direct_http',
                'html_strategy'      => $htmlStrategy,
                'blocked_or_captcha' => $fetchResult['blocked_or_captcha'] ?? false,
            ];

            // ── Extract ──────────────────────────────────────────────────────────
            $timing['extract_start'] = microtime(true);
            $product                 = $extractionService->extract($html, $url, $storeKey, $strategy, $fetchMetadata);
            $timing['extract_ms']    = round((microtime(true) - $timing['extract_start']) * 1000);

            $extractionSource = $product['extraction_source'] ?? 'unknown';
            $providerAttempts[] = [
                'provider' => $extractionSource,
                'strategy' => 'extraction',
                'success'  => ($product['name'] ?? '') !== '' && ($product['name'] ?? '') !== 'Product',
                'note'     => 'name=' . ($product['name'] ?? '—') . ', price=' . ($product['price'] ?? 0),
            ];

            // Pass-through fetch metadata
            $product['fetch_source']       = $product['fetch_source']       ?? $fetchResult['fetch_source'] ?? 'direct_http';
            $product['html_strategy']      = $product['html_strategy']       ?? $htmlStrategy;
            $product['blocked_or_captcha'] = $product['blocked_or_captcha']  ?? $fetchResult['blocked_or_captcha'] ?? false;

            // ScraperAPI raw keys (for debug tab)
            $raw = $product['scraperapi_raw'] ?? [];
            $product['_scraperapi_raw_keys'] = is_array($raw) ? array_keys($raw) : [];

            // ── Shipping quote ───────────────────────────────────────────────────
            $timing['shipping_start'] = microtime(true);
            $shippingOverrides = array_filter([
                'destination_country' => $validated['destination_country'] ?? null,
                'quantity'            => $validated['quantity'] ?? null,
                'carrier'             => $validated['carrier'] ?? null,
            ], fn ($v) => $v !== null);

            $product['shipping_quote'] = $shippingQuoteService->quoteFromProduct(
                $product,
                $shippingOverrides,
                $extractionSource
            );
            $timing['shipping_ms'] = round((microtime(true) - $timing['shipping_start']) * 1000);

            // ── Final pricing ────────────────────────────────────────────────────
            $product['final_pricing'] = null;
            if ($product['shipping_quote'] !== null) {
                try {
                    $qty     = max(1, (int) ($validated['quantity'] ?? $product['quantity'] ?? 1));
                    $pricing = $finalPricingService->build($product, $product['shipping_quote'], $qty);
                    $product['final_pricing'] = $pricing?->toArray();
                } catch (\Throwable $e) {
                    $product['final_pricing'] = ['error' => $e->getMessage()];
                }
            }

            // ── Shipping review ──────────────────────────────────────────────────
            $shippingReviewRequired = true;
            if (is_array($product['shipping_quote'] ?? null)) {
                $missingFields          = $product['shipping_quote']['missing_fields'] ?? [];
                $isEstimated            = (bool) ($product['shipping_quote']['estimated'] ?? true);
                $shippingReviewRequired = $isEstimated || $missingFields !== [];
            }
            $product['shipping_review_required'] = $shippingReviewRequired;

            // ── Measurements & shipping source ───────────────────────────────────
            if (! isset($product['weight']))     { $product['weight']     = null; }
            if (! isset($product['dimensions'])) { $product['dimensions'] = null; }

            $hasMeasurements                    = $product['weight'] !== null && $product['dimensions'] !== null;
            $product['measurements_found']      = $hasMeasurements;
            $product['shipping_estimate_source'] = $hasMeasurements ? 'exact' : 'fallback';

            $timing['total_ms'] = round((microtime(true) - $timing['started_at']) * 1000);

            // Separate scraperapi_raw from main product (too large for inline display)
            $scraperApiRaw = $product['scraperapi_raw'] ?? null;
            unset($product['scraperapi_raw']);

            return response()->json([
                'ok'               => true,
                'store_resolution' => $this->buildStoreResolution($url, $storeKey, $asin, $tld),
                'provider_attempts'=> $providerAttempts,
                'timing'           => [
                    'fetch_ms'    => $timing['fetch_ms'],
                    'extract_ms'  => $timing['extract_ms'],
                    'shipping_ms' => $timing['shipping_ms'],
                    'total_ms'    => $timing['total_ms'],
                ],
                'product'          => $product,
                'html_length'      => strlen($html),
                'scraperapi_raw'   => $scraperApiRaw,
            ]);

        } catch (\Throwable $e) {
            Log::error('ProductImportTester error', ['url' => $url, 'message' => $e->getMessage()]);

            return response()->json([
                'ok'               => false,
                'error'            => $e->getMessage(),
                'store_resolution' => $this->buildStoreResolution($url, $storeKey, $asin, $tld),
                'provider_attempts'=> $providerAttempts,
                'trace'            => config('app.debug')
                    ? collect(explode("\n", $e->getTraceAsString()))->take(10)->values()
                    : null,
            ], 500);
        }
    }

    /**
     * Build the store resolution debug block.
     */
    private function buildStoreResolution(string $url, string $storeKey, ?string $asin, ?string $tld): array
    {
        $hasKey = ! empty(config('services.product_import.scraperapi_key'));

        return [
            'original_url'   => $url,
            'store_key'      => $storeKey,
            'asin'           => $asin,
            'amazon_tld'     => $tld,
            'primary_provider' => match ($storeKey) {
                'amazon'     => $hasKey ? 'scraperapi_structured' : 'html_pipeline',
                'aliexpress' => 'scraperapi_rendered',
                'ebay', 'walmart' => 'html_pipeline + scraperapi_structured (fallback)',
                default      => 'html_pipeline',
            },
            'scraperapi_key_configured' => $hasKey,
        ];
    }
}
