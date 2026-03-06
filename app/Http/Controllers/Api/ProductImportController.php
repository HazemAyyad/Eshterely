<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProductImportController extends Controller
{
    /**
     * Import product data from URL. Uses hybrid pipeline: JSON-LD -> meta tags -> DOM -> OpenAI -> regex.
     */
    public function importFromUrl(Request $request, ProductExtractionService $extractionService): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url',
            'store_key' => 'nullable|string',
        ]);

        $url = $validated['url'];

        try {
            $response = Http::timeout(15)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ])->get($url);

            if (!$response->successful()) {
                return response()->json(['message' => 'Could not fetch URL'], 400);
            }

            $html = $response->body();
            $storeKey = $validated['store_key'] ?? $this->detectStoreKey($url);

            $product = $extractionService->extract($html, $url, $storeKey);

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
