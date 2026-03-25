<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductExtractionService;
use App\Services\ProductPageFetcherService;
use App\Services\ProductImport\ImportAttemptOrchestrator;
use App\Services\ProductImport\StoreResolver;
use App\Services\Shipping\FinalProductPricingService;
use App\Services\Shipping\ProductImportShippingQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ProductImportController extends Controller
{
    /**
     * Import product data from URL.
     * Pipeline: structured_data → json_ld → open_graph → direct_html → ai_extraction → paid_scraper.
     * Per-store settings are read from product_import_store_settings.
     * Each attempt is logged to product_import_logs.
     * Response includes shipping quote preview, pricing, and shipping review fields.
     */
    public function importFromUrl(
        Request $request,
        ProductExtractionService $extractionService,
        ProductPageFetcherService $pageFetcher,
        ProductImportShippingQuoteService $shippingQuoteService,
        FinalProductPricingService $finalPricingService,
        ImportAttemptOrchestrator $orchestrator,
    ): JsonResponse {
        $validated = $request->validate([
            'url' => 'required|url',
            'store_key' => 'nullable|string',
            'extraction_strategy' => 'nullable|string|in:auto,jsonld,meta,dom,openai',
            'destination_country' => 'nullable|string|max:10',
            'warehouse_mode' => 'nullable|boolean',
            'quantity' => 'nullable|integer|min:1',
            'carrier' => 'nullable|string|in:dhl,ups,fedex,auto',
        ]);

        $url = $validated['url'];
        $storeKey = $validated['store_key'] ?? StoreResolver::resolve($url);
        $strategy = $validated['extraction_strategy'] ?? 'auto';

        // Log the import attempt via orchestrator (non-blocking).
        $orchestrator->beginAttempt($url, $storeKey);

        try {
            $fetchResult = $pageFetcher->fetchHtml($url, $storeKey);
            $html = $fetchResult['html'] ?? '';

            if ($html === '') {
                return response()->json(['message' => 'Could not fetch URL'], 400);
            }

            $fetchMetadata = [
                'fetch_source' => $fetchResult['fetch_source'] ?? 'direct_http',
                'html_strategy' => $fetchResult['html_strategy'] ?? 'initial_html',
                'blocked_or_captcha' => $fetchResult['blocked_or_captcha'] ?? false,
            ];
            $product = $extractionService->extract($html, $url, $storeKey, $strategy, $fetchMetadata);

            if (! isset($product['fetch_source'])) {
                $product['fetch_source'] = $fetchResult['fetch_source'] ?? 'direct_http';
            }
            if (! isset($product['html_strategy'])) {
                $product['html_strategy'] = $fetchResult['html_strategy'] ?? 'initial_html';
            }
            if (! array_key_exists('blocked_or_captcha', $product)) {
                $product['blocked_or_captcha'] = $fetchResult['blocked_or_captcha'] ?? false;
            }

            $raw = $product['scraperapi_raw'] ?? [];
            if (is_array($raw) && $raw !== []) {
                $product['variations'] = $this->extractVariationsFromRaw($raw);
            }

            $shippingOverrides = array_filter([
                'destination_country' => $validated['destination_country'] ?? null,
                'warehouse_mode' => isset($validated['warehouse_mode']) ? (bool) $validated['warehouse_mode'] : null,
                'quantity' => $validated['quantity'] ?? null,
                'carrier' => $validated['carrier'] ?? null,
            ], fn ($v) => $v !== null);

            $product['shipping_quote'] = $shippingQuoteService->quoteFromProduct(
                $product,
                $shippingOverrides,
                $product['extraction_source'] ?? null
            );

            $product['final_pricing'] = null;
            if ($product['shipping_quote'] !== null) {
                try {
                    $quantity = (int) ($validated['quantity'] ?? $product['quantity'] ?? 1);
                    $quantity = $quantity < 1 ? 1 : $quantity;
                    $pricing = $finalPricingService->build($product, $product['shipping_quote'], $quantity);
                    $product['final_pricing'] = $pricing !== null ? $pricing->toArray() : null;
                } catch (\Throwable $e) {
                    Log::warning('Product import: final pricing calculation failed', [
                        'message' => $e->getMessage(),
                        'url' => $url,
                    ]);
                }
            }

            // Single trustworthy pricing structure for the app (keeps legacy keys too)
            $currency = strtoupper(trim((string) ($product['currency'] ?? 'USD')));
            if ($currency === '') {
                $currency = 'USD';
            }
            $quantity = (int) ($validated['quantity'] ?? $product['quantity'] ?? 1);
            $quantity = $quantity < 1 ? 1 : $quantity;

            $unitPrice = is_numeric($product['price'] ?? null) ? (float) $product['price'] : 0.0;
            $lineSubtotal = round($unitPrice * $quantity, 2);
            $shippingAmount = is_array($product['shipping_quote'] ?? null) && isset($product['shipping_quote']['amount'])
                ? (float) $product['shipping_quote']['amount']
                : 0.0;
            $shippingEstimated = (bool) ($product['shipping_quote']['estimated'] ?? false);
            $needsReview = (bool) ($product['shipping_quote']['missing_fields'] ?? false) || $shippingEstimated;

            $finalTotal = is_array($product['final_pricing'] ?? null) && isset($product['final_pricing']['final_total'])
                ? (float) $product['final_pricing']['final_total']
                : round($lineSubtotal + $shippingAmount, 2);

            $product['pricing'] = [
                'currency' => $currency,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'subtotal' => $lineSubtotal,
                'shipping_amount' => round($shippingAmount, 2),
                'shipping_estimated' => $shippingEstimated,
                'needs_review' => $needsReview,
                'total' => round($finalTotal, 2),
                'breakdown' => array_values(array_filter([
                    ['key' => 'product', 'label' => 'Product', 'amount' => $lineSubtotal],
                    ['key' => 'shipping', 'label' => 'Shipping', 'amount' => round($shippingAmount, 2), 'estimated' => $shippingEstimated],
                    is_array($product['final_pricing'] ?? null) && isset($product['final_pricing']['service_fee'])
                        ? ['key' => 'service_fee', 'label' => 'Service fee', 'amount' => (float) $product['final_pricing']['service_fee']]
                        : null,
                    is_array($product['final_pricing'] ?? null) && isset($product['final_pricing']['markup_amount'])
                        ? ['key' => 'markup', 'label' => 'Markup', 'amount' => (float) $product['final_pricing']['markup_amount']]
                        : null,
                ])),
            ];

            // --- Shipping review fields ---
            // shipping_review_required = true when weight/dimensions are missing (estimated quote)
            // or when the quote itself is null (no data at all).
            $shippingReviewRequired = true;
            if (is_array($product['shipping_quote'] ?? null)) {
                $missingFields = $product['shipping_quote']['missing_fields'] ?? [];
                $isEstimated   = (bool) ($product['shipping_quote']['estimated'] ?? true);
                $shippingReviewRequired = $isEstimated || $missingFields !== [];
            }

            $product['shipping_review_required'] = $shippingReviewRequired;
            $product['shipping_note_ar'] = 'سعر الشحن المعروض حاليًا تقديري فقط، وسيتم مراجعته واعتماده من الإدارة بعد فحص المنتج والمواصفات.';
            $product['shipping_note_en'] = 'The shipping cost shown is an estimate only and will be reviewed and confirmed by admin after inspecting the product and its specifications.';

            // --- Weight / dimensions pass-through ---
            // Only include if the extraction pipeline found them (never guess).
            if (! isset($product['weight'])) {
                $product['weight'] = null;
            }
            if (! isset($product['dimensions'])) {
                $product['dimensions'] = null;
            }

            // Log successful attempt.
            $orchestrator->recordSuccess($url, $storeKey, $product['extraction_source'] ?? 'unknown');

            return response()->json($product);
        } catch (\Exception $e) {
            $orchestrator->recordFailure($url, $storeKey ?? 'unknown', $e->getMessage());

            return response()->json([
                'message' => 'Import failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Build variations array for Flutter from scraperapi_raw (size/color options).
     * Supports: Amazon customization_options (color/size), eBay color + item_specifics,
     * and generic variations[] / size_options / color_options.
     *
     * @param  array<string, mixed>  $raw
     * @return array<int, array{type: string, options: array<int, string>, prices?: array<int, float>}>
     */
    private function extractVariationsFromRaw(array $raw): array
    {
        $out = [];

        // --- Amazon: scraperapi_raw.customization_options ---
        $custom = $raw['customization_options'] ?? null;
        if (is_array($custom)) {
            if (isset($custom['color']) && is_array($custom['color'])) {
                $colorOpts = [];
                foreach ($custom['color'] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $value = $item['value'] ?? null;
                    if ($value !== null && trim((string) $value) !== '') {
                        $colorOpts[] = trim((string) $value);
                    }
                }
                $colorOpts = array_values(array_unique($colorOpts));
                if ($colorOpts !== []) {
                    $out[] = ['type' => 'color', 'options' => $colorOpts];
                }
            }
            if (isset($custom['size']) && is_array($custom['size'])) {
                $sizeOpts = [];
                foreach ($custom['size'] as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $value = $item['value'] ?? $item['label'] ?? null;
                    if ($value !== null && trim((string) $value) !== '') {
                        $sizeOpts[] = trim((string) $value);
                    } else {
                        $sizeOpts[] = 'Size ' . ((int) $index + 1);
                    }
                }
                $sizeOpts = array_values(array_unique($sizeOpts));
                if ($sizeOpts !== []) {
                    $out[] = ['type' => 'size', 'options' => $sizeOpts];
                }
            }
        }

        // --- eBay: top-level color + item_specifics (Size, Color, etc.) ---
        if (isset($raw['color']) && trim((string) $raw['color']) !== '') {
            $c = trim((string) $raw['color']);
            if (! $this->variationOptionAlreadyAdded($out, 'color', $c)) {
                $out[] = ['type' => 'color', 'options' => [$c]];
            }
        }
        if (isset($raw['item_specifics']) && is_array($raw['item_specifics'])) {
            $byLabel = [];
            foreach ($raw['item_specifics'] as $spec) {
                if (! is_array($spec)) {
                    continue;
                }
                $label = isset($spec['label']) ? trim((string) $spec['label']) : '';
                $value = isset($spec['value']) ? trim((string) $spec['value']) : '';
                if ($label === '' || $value === '') {
                    continue;
                }
                $labelLower = strtolower($label);
                if ($labelLower === 'size' || $labelLower === 'color' || $labelLower === 'colour') {
                    $type = $labelLower === 'size' ? 'size' : 'color';
                    if (! isset($byLabel[$type])) {
                        $byLabel[$type] = [];
                    }
                    if (! in_array($value, $byLabel[$type], true)) {
                        $byLabel[$type][] = $value;
                    }
                }
            }
            foreach ($byLabel as $type => $opts) {
                if ($opts !== [] && ! $this->variationTypeAlreadyAdded($out, $type)) {
                    $out[] = ['type' => $type, 'options' => array_values($opts)];
                }
            }
        }

        // --- Generic: variations[] ---
        if (isset($raw['variations']) && is_array($raw['variations'])) {
            foreach ($raw['variations'] as $v) {
                if (! is_array($v)) {
                    continue;
                }
                $type = (string) ($v['type'] ?? $v['label'] ?? 'option');
                $opts = $v['options'] ?? $v['values'] ?? [];
                $opts = is_array($opts) ? array_map('strval', array_values($opts)) : [];
                $prices = isset($v['prices']) && is_array($v['prices'])
                    ? array_values(array_map(function ($p) {
                        return is_numeric($p) ? (float) $p : 0.0;
                    }, $v['prices']))
                    : null;
                if ($opts !== [] && ! $this->variationTypeAlreadyAdded($out, $type)) {
                    $item = ['type' => $type, 'options' => $opts];
                    if ($prices !== null && $prices !== []) {
                        $item['prices'] = $prices;
                    }
                    $out[] = $item;
                }
            }
        }

        if (isset($raw['size_options']) && is_array($raw['size_options']) && ! $this->variationTypeAlreadyAdded($out, 'size')) {
            $opts = array_map('strval', array_values($raw['size_options']));
            if ($opts !== []) {
                $out[] = ['type' => 'size', 'options' => $opts];
            }
        }
        if (isset($raw['color_options']) && is_array($raw['color_options']) && ! $this->variationTypeAlreadyAdded($out, 'color')) {
            $opts = array_map('strval', array_values($raw['color_options']));
            if ($opts !== []) {
                $out[] = ['type' => 'color', 'options' => $opts];
            }
        }

        $info = $raw['product_information'] ?? [];
        if (is_array($info) && isset($info['variants']) && is_array($info['variants'])) {
            foreach ($info['variants'] as $v) {
                if (! is_array($v)) {
                    continue;
                }
                $type = (string) ($v['dimension'] ?? $v['type'] ?? 'option');
                $opts = $v['options'] ?? $v['values'] ?? [];
                $opts = is_array($opts) ? array_map('strval', array_values($opts)) : [];
                if ($opts !== [] && ! $this->variationTypeAlreadyAdded($out, $type)) {
                    $out[] = ['type' => $type, 'options' => $opts];
                }
            }
        }

        return array_values($out);
    }

    private function variationOptionAlreadyAdded(array $out, string $type, string $option): bool
    {
        foreach ($out as $v) {
            if (($v['type'] ?? '') === $type && in_array($option, $v['options'] ?? [], true)) {
                return true;
            }
        }
        return false;
    }

    private function variationTypeAlreadyAdded(array $out, string $type): bool
    {
        foreach ($out as $v) {
            if (($v['type'] ?? '') === $type) {
                return true;
            }
        }
        return false;
    }
}
