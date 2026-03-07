<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductExtractionService;
use App\Services\ProductPageFetcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductImportController extends Controller
{
    /**
     * Import product data from URL. Uses hybrid pipeline: JSON-LD -> meta tags -> DOM -> OpenAI -> regex.
     * Fetching is delegated to ProductPageFetcherService (direct HTTP or, for Amazon, rendered fetch when configured).
     */
    public function importFromUrl(Request $request, ProductExtractionService $extractionService, ProductPageFetcherService $pageFetcher): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url',
            'store_key' => 'nullable|string',
        ]);

        $url = $validated['url'];
        $storeKey = $validated['store_key'] ?? $this->detectStoreKey($url);

        try {
            $fetchResult = $pageFetcher->fetchHtml($url, $storeKey);
            $html = $fetchResult['html'] ?? '';

            if ($html === '') {
                return response()->json(['message' => 'Could not fetch URL'], 400);
            }

            $product = $extractionService->extract($html, $url, $storeKey);

            $product['fetch_source'] = $fetchResult['fetch_source'] ?? 'direct_http';
            $product['html_strategy'] = $fetchResult['html_strategy'] ?? 'initial_html';
            $product['blocked_or_captcha'] = $fetchResult['blocked_or_captcha'] ?? false;

            return response()->json($product);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Import failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    private function detectStoreKey(string $url): string
    {
        if (Str::contains($url, 'amazon.')) return 'amazon';
        if (Str::contains($url, 'ebay.')) return 'ebay';
        if (Str::contains($url, 'walmart.')) return 'walmart';
        if (Str::contains($url, 'etsy.')) return 'etsy';
        if (Str::contains($url, 'aliexpress.')) return 'aliexpress';
        if (Str::contains($url, 'trendyol.')) return 'trendyol';
        return 'unknown';
    }
}
