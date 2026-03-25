<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Services\ProductExtractionService;
use App\Services\ProductPageFetcherService;
use App\Services\ProductImport\StoreResolver;
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

        $timing = ['started_at' => microtime(true)];

        try {
            // --- Fetch ---
            $timing['fetch_start'] = microtime(true);
            $fetchResult = $pageFetcher->fetchHtml($url, $storeKey);
            $timing['fetch_ms'] = round((microtime(true) - $timing['fetch_start']) * 1000);

            $html = $fetchResult['html'] ?? '';

            if ($html === '') {
                return response()->json([
                    'ok'    => false,
                    'error' => 'Could not fetch the URL — empty HTML returned.',
                    'meta'  => ['store_key' => $storeKey, 'fetch_source' => $fetchResult['fetch_source'] ?? null],
                ], 422);
            }

            $fetchMetadata = [
                'fetch_source'     => $fetchResult['fetch_source'] ?? 'direct_http',
                'html_strategy'    => $fetchResult['html_strategy'] ?? 'initial_html',
                'blocked_or_captcha' => $fetchResult['blocked_or_captcha'] ?? false,
            ];

            // --- Extract ---
            $timing['extract_start'] = microtime(true);
            $product = $extractionService->extract($html, $url, $storeKey, $strategy, $fetchMetadata);
            $timing['extract_ms'] = round((microtime(true) - $timing['extract_start']) * 1000);

            // Pass-through fetch metadata
            $product['fetch_source']      = $product['fetch_source']      ?? $fetchResult['fetch_source'] ?? 'direct_http';
            $product['html_strategy']     = $product['html_strategy']      ?? $fetchResult['html_strategy'] ?? 'initial_html';
            $product['blocked_or_captcha']= $product['blocked_or_captcha'] ?? $fetchResult['blocked_or_captcha'] ?? false;

            // Variations from raw
            $raw = $product['scraperapi_raw'] ?? [];
            $product['_scraperapi_raw_keys'] = is_array($raw) ? array_keys($raw) : [];

            // --- Shipping quote ---
            $timing['shipping_start'] = microtime(true);
            $shippingOverrides = array_filter([
                'destination_country' => $validated['destination_country'] ?? null,
                'quantity'            => $validated['quantity'] ?? null,
                'carrier'             => $validated['carrier'] ?? null,
            ], fn($v) => $v !== null);

            $product['shipping_quote'] = $shippingQuoteService->quoteFromProduct(
                $product,
                $shippingOverrides,
                $product['extraction_source'] ?? null
            );
            $timing['shipping_ms'] = round((microtime(true) - $timing['shipping_start']) * 1000);

            // --- Final pricing ---
            $product['final_pricing'] = null;
            if ($product['shipping_quote'] !== null) {
                try {
                    $qty = max(1, (int) ($validated['quantity'] ?? $product['quantity'] ?? 1));
                    $pricing = $finalPricingService->build($product, $product['shipping_quote'], $qty);
                    $product['final_pricing'] = $pricing?->toArray();
                } catch (\Throwable $e) {
                    $product['final_pricing'] = ['error' => $e->getMessage()];
                }
            }

            // --- Shipping review ---
            $shippingReviewRequired = true;
            if (is_array($product['shipping_quote'] ?? null)) {
                $missingFields = $product['shipping_quote']['missing_fields'] ?? [];
                $isEstimated   = (bool) ($product['shipping_quote']['estimated'] ?? true);
                $shippingReviewRequired = $isEstimated || $missingFields !== [];
            }
            $product['shipping_review_required'] = $shippingReviewRequired;

            $timing['total_ms'] = round((microtime(true) - $timing['started_at']) * 1000);

            // Remove scraperapi_raw from main response (too large) — show summary instead
            $scraperApiRaw = $product['scraperapi_raw'] ?? null;
            unset($product['scraperapi_raw']);

            return response()->json([
                'ok'              => true,
                'store_key'       => $storeKey,
                'timing'          => [
                    'fetch_ms'    => $timing['fetch_ms'],
                    'extract_ms'  => $timing['extract_ms'],
                    'shipping_ms' => $timing['shipping_ms'],
                    'total_ms'    => $timing['total_ms'],
                ],
                'product'         => $product,
                'html_length'     => strlen($html),
                'scraperapi_raw'  => $scraperApiRaw,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProductImportTester error', ['url' => $url, 'message' => $e->getMessage()]);
            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? collect(explode("\n", $e->getTraceAsString()))->take(10)->values() : null,
            ], 500);
        }
    }
}
