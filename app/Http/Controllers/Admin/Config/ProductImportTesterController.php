<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Services\ProductExtractionService;
use App\Services\ProductPageFetcherService;
use App\Services\ProductImport\StoreResolver;
use App\Services\ProductImport\ImportOrchestrator;
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
        ProductImportShippingQuoteService $shippingQuoteService,
        FinalProductPricingService $finalPricingService,
        StructuredProductImportService $structuredService,
        ImportOrchestrator $importOrchestrator,
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
            // ── Import pipeline (same as API) ────────────────────────────────────
            $timing['import_start'] = microtime(true);
            $import = $importOrchestrator->import($url, $storeKey, [
                'extraction_strategy' => $strategy,
            ]);
            $timing['extract_ms'] = round((microtime(true) - $timing['import_start']) * 1000);

            $product = $import['product'];
            $debug = $import['debug'];

            // Provider attempts from orchestrator (ordered)
            $providerAttempts = $debug['provider_attempts'] ?? [];

            // ScraperAPI raw keys (for debug tab)
            $raw = $debug['scraperapi_raw'] ?? ($product['scraperapi_raw'] ?? []);
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
                $product['extraction_source'] ?? null
            );
            $timing['shipping_ms'] = round((microtime(true) - $timing['shipping_start']) * 1000);

            $amount = is_array($product['shipping_quote'] ?? null) && isset($product['shipping_quote']['amount'])
                ? (float) $product['shipping_quote']['amount']
                : null;
            $product['shipping_estimate'] = [
                'amount' => $amount,
                'source' => ($product['shipping_estimate_source'] ?? 'fallback') === 'exact' ? 'exact' : 'fallback',
                'note' => ($product['shipping_estimate_source'] ?? 'fallback') === 'exact'
                    ? 'exact measurements'
                    : 'fallback defaults',
            ];

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
            $product['provider_used'] = $debug['provider_used'] ?? ($product['extraction_source'] ?? 'unknown');
            $product['warnings'] = $debug['warnings'] ?? [];
            $product['asin'] = $debug['asin'] ?? null;
            $product['ai_parsed_json'] = $debug['ai_parsed_json'] ?? null;
            $product['normalized_url'] = $product['canonical_url'] ?? $url;

            $timing['total_ms'] = round((microtime(true) - $timing['started_at']) * 1000);

            // Separate scraperapi_raw from main product (too large for inline display)
            $scraperApiRaw = $raw ?: ($product['scraperapi_raw'] ?? null);
            unset($product['scraperapi_raw']);

            return response()->json([
                'ok'               => true,
                'store_resolution' => $this->buildStoreResolution($url, $storeKey, $asin, $tld),
                'provider_attempts'=> $providerAttempts,
                'timing'           => [
                    'fetch_ms'    => null,
                    'extract_ms'  => $timing['extract_ms'],
                    'shipping_ms' => $timing['shipping_ms'],
                    'total_ms'    => $timing['total_ms'],
                ],
                'product'          => $product,
                'html_length'      => null,
                'scraperapi_raw'   => $scraperApiRaw,
                'ai_parsed_json'   => $product['ai_parsed_json'] ?? null,
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
