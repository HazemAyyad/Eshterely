<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProductImportController extends Controller
{
    /**
     * Import product data from URL. Fetches page content and uses AI/extraction to get product fields.
     * Placeholder: returns mock data. Integrate with AI (OpenAI/Claude) or Scrapling for real extraction.
     */
    public function importFromUrl(Request $request): JsonResponse
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

            // TODO: Send $html to AI (OpenAI/Claude) with prompt to extract product fields as JSON.
            // For now return a placeholder structure matching ProductImportResult / CartItem.
            $product = $this->extractFromHtmlPlaceholder($html, $url, $storeKey);

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

    private function extractFromHtmlPlaceholder(string $html, string $url, string $storeKey): array
    {
        // Placeholder: parse basic meta tags. Replace with AI extraction.
        $title = 'Product';
        if (preg_match('/<meta[^>]+property="og:title"[^>]+content="([^"]+)"/', $html, $m)) {
            $title = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        } elseif (preg_match('/<title>([^<]+)<\/title>/', $html, $m)) {
            $title = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        }

        $imageUrl = null;
        if (preg_match('/<meta[^>]+property="og:image"[^>]+content="([^"]+)"/', $html, $m)) {
            $imageUrl = $m[1];
        }

        $price = 0.0;
        if (preg_match('/"price"\s*:\s*["\']?([\d.]+)/', $html, $m)) {
            $price = (float) $m[1];
        } elseif (preg_match('/\$([\d,]+\.?\d*)/', $html, $m)) {
            $price = (float) str_replace(',', '', $m[1]);
        }

        $country = match ($storeKey) {
            'amazon', 'ebay', 'walmart', 'etsy' => 'USA',
            'aliexpress' => 'China',
            'trendyol' => 'Turkey',
            default => 'Unknown',
        };

        return [
            'name' => $title,
            'price' => $price,
            'currency' => 'USD',
            'store_name' => ucfirst($storeKey),
            'country' => $country,
            'image_url' => $imageUrl,
            'canonical_url' => $url,
            'store_key' => $storeKey,
        ];
    }
}
