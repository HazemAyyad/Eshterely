<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Symfony\Component\DomCrawler\Crawler;

class ProductExtractionService
{
    private const MAX_HTML_LENGTH = 15000;

    /**
     * Failure reason when forced OpenAI extraction is skipped due to blocked/incomplete HTML.
     */
    private const OPENAI_BAD_INPUT_FAILURE_REASON = 'OpenAI forced extraction received blocked or incomplete HTML, not full rendered product content.';

    /**
     * Main entry point. With strategy 'auto': JSON-LD → Meta → DOM → OpenAI → Regex.
     * With a forced strategy (jsonld, meta, dom, openai): run only that method; on failure return strategy_failed.
     * For strategy 'openai', fetch metadata is used to avoid pretending OpenAI failed when HTML is blocked/incomplete.
     *
     * @param  array{fetch_source?: string, html_strategy?: string, blocked_or_captcha?: bool}|null  $fetchMetadata
     * @return array<string, mixed>
     */
    public function extract(
        string $html,
        string $url,
        string $storeKey,
        string $strategy = 'auto',
        ?array $fetchMetadata = null
    ): array {
        $strategy = strtolower($strategy);
        if ($strategy === '') {
            $strategy = 'auto';
        }

        // Forced strategies: run only the requested method; on invalid result return strategy_failed.
        if ($strategy === 'jsonld') {
            $data = $this->extractFromJsonLd($html);
            if ($this->isValidResult($data)) {
                return $this->normalizeResult($data, $url, $storeKey, 'jsonld_forced');
            }
            return $this->normalizeResult(
                ['name' => 'Product', 'price' => 0],
                $url,
                $storeKey,
                'strategy_failed'
            );
        }

        if ($strategy === 'meta') {
            $data = $this->extractFromMetaTags($html);
            if ($this->isValidResult($data)) {
                return $this->normalizeResult($data, $url, $storeKey, 'meta_forced');
            }
            return $this->normalizeResult(
                ['name' => 'Product', 'price' => 0],
                $url,
                $storeKey,
                'strategy_failed'
            );
        }

        if ($strategy === 'dom') {
            $data = $this->extractFromDom($html, $storeKey);
            if ($this->isValidResult($data)) {
                return $this->normalizeResult($data, $url, $storeKey, 'dom_forced');
            }
            return $this->normalizeResult(
                ['name' => 'Product', 'price' => 0],
                $url,
                $storeKey,
                'strategy_failed'
            );
        }

        if ($strategy === 'openai') {
            $failureReason = $this->openaiForcedFailureReason($html, $storeKey, $fetchMetadata);
            if ($failureReason !== null) {
                $result = $this->normalizeResult(
                    ['name' => 'Product', 'price' => 0],
                    $url,
                    $storeKey,
                    'strategy_failed'
                );
                $result['failure_reason'] = $failureReason;

                return $result;
            }

            $data = $this->extractWithOpenAI($html, $url, $storeKey);
            if ($this->isValidResult($data)) {
                return $this->normalizeResult($data, $url, $storeKey, 'openai_forced');
            }
            $result = $this->normalizeResult(
                ['name' => 'Product', 'price' => 0],
                $url,
                $storeKey,
                'strategy_failed'
            );
            $result['failure_reason'] = 'OpenAI extraction did not return valid product data.';

            return $result;
        }

        // strategy === 'auto': existing pipeline unchanged
        $data = $this->extractFromJsonLd($html);
        if ($this->isValidResult($data)) {
            return $this->normalizeResult($data, $url, $storeKey, 'json_ld');
        }

        $data = $this->extractFromMetaTags($html);
        if ($this->isValidResult($data)) {
            $domData = $this->extractFromDom($html, $storeKey);
            $merged = $this->mergeMetaWithDom($data, $domData, $url, $storeKey);
            return $this->normalizeResult($merged['data'], $url, $storeKey, $merged['source']);
        }

        $data = $this->extractFromDom($html, $storeKey);
        if ($this->isValidResult($data)) {
            return $this->normalizeResult($data, $url, $storeKey, 'dom');
        }

        $data = $this->extractWithOpenAI($html, $url, $storeKey);
        if ($this->isValidResult($data)) {
            return $this->normalizeResult($data, $url, $storeKey, 'openai');
        }

        return $this->normalizeResult(
            $this->extractFromRegex($html),
            $url,
            $storeKey,
            'regex'
        );
    }

    /**
     * When strategy is openai, determine if we should skip OpenAI and return strategy_failed with a clear reason.
     * Returns the failure_reason string if input is blocked/incomplete; null otherwise (run OpenAI).
     *
     * @param  array{fetch_source?: string, html_strategy?: string, blocked_or_captcha?: bool}|null  $fetchMetadata
     */
    private function openaiForcedFailureReason(string $html, string $storeKey, ?array $fetchMetadata): ?string
    {
        if ($fetchMetadata === null) {
            return null;
        }

        $blockedOrCaptcha = $fetchMetadata['blocked_or_captcha'] ?? false;
        if ($blockedOrCaptcha) {
            return self::OPENAI_BAD_INPUT_FAILURE_REASON;
        }

        $storeKeyLower = strtolower($storeKey);
        $htmlStrategy = $fetchMetadata['html_strategy'] ?? 'initial_html';
        if ($storeKeyLower === 'amazon' && $htmlStrategy === 'initial_html' && $this->isAmazonHtmlIncomplete($html)) {
            return self::OPENAI_BAD_INPUT_FAILURE_REASON;
        }

        return null;
    }

    /**
     * Heuristic: Amazon product page HTML from direct fetch (initial shell) often lacks full product DOM.
     */
    private function isAmazonHtmlIncomplete(string $html): bool
    {
        $len = strlen($html);
        if ($len < 5000) {
            return true;
        }
        $lower = strtolower($html);
        $hasProductMarkers = str_contains($lower, 'coreprice')
            || str_contains($lower, 'pricetopay')
            || str_contains($lower, 'producttitle');

        return ! $hasProductMarkers;
    }

    /**
     * Result is valid if we have a name AND at least price or image.
     * Incomplete results (price=0 and image=null) trigger next extraction method.
     */
    public function isValidResult(?array $data): bool
    {
        if ($data === null || ! is_array($data)) {
            return false;
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return false;
        }
        $price = (float) ($data['price'] ?? 0);
        $imageUrl = $data['image_url'] ?? null;
        $hasPrice = $price > 0;
        $hasImage = $imageUrl !== null && trim((string) $imageUrl) !== '';

        return $hasPrice || $hasImage;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeResult(array $data, string $url, string $storeKey, string $source): array
    {
        $name = trim((string) ($data['name'] ?? 'Product'));
        if ($name === '') {
            $name = 'Product';
        } else {
            $cleaned = $this->stripProductTitleNoise($name);
            if ($cleaned !== '') {
                $name = $cleaned;
            }
        }

        $price = isset($data['price']) ? (float) $data['price'] : 0.0;
        $currency = (string) ($data['currency'] ?? 'USD');
        if (strlen($currency) > 10) {
            $currency = 'USD';
        }

        $imageUrl = null;
        if (isset($data['image_url']) && $data['image_url'] !== null && $data['image_url'] !== '') {
            $urlStr = (string) $data['image_url'];
            if (filter_var($urlStr, FILTER_VALIDATE_URL)) {
                $imageUrl = $urlStr;
            }
        }

        return [
            'name' => $name,
            'price' => $price,
            'currency' => $currency,
            'image_url' => $imageUrl,
            'store_key' => $storeKey,
            'store_name' => ucfirst($storeKey),
            'country' => $this->getCountryForStore($storeKey),
            'canonical_url' => $url,
            'extraction_source' => $source,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function extractFromJsonLd(string $html): ?array
    {
        if (! preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches)) {
            return null;
        }

        foreach ($matches[1] as $jsonStr) {
            $jsonStr = trim($jsonStr);
            $decoded = json_decode($jsonStr, true);
            if (! is_array($decoded)) {
                continue;
            }
            $result = $this->parseJsonLdProduct($decoded);
            if ($result !== null) {
                return $result;
            }
            if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
                foreach ($decoded['@graph'] as $item) {
                    if (is_array($item)) {
                        $result = $this->parseJsonLdProduct($item);
                        if ($result !== null) {
                            return $result;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $obj
     * @return array<string, mixed>|null
     */
    private function parseJsonLdProduct(array $obj): ?array
    {
        $type = $obj['@type'] ?? null;
        $isProduct = $type === 'Product' || (is_array($type) && in_array('Product', $type, true));

        if (! $isProduct && ! (isset($obj['name']) && (isset($obj['offers']) || isset($obj['image'])))) {
            return null;
        }

        $name = $obj['name'] ?? null;
        if ($name === null || trim((string) $name) === '') {
            return null;
        }

        $price = 0.0;
        $currency = 'USD';
        $offers = $obj['offers'] ?? null;
        if (is_array($offers)) {
            $offer = isset($offers[0]) ? $offers[0] : $offers;
            if (is_array($offer)) {
                $price = (float) ($offer['price'] ?? $offers['price'] ?? 0);
                $currency = (string) ($offer['priceCurrency'] ?? $offers['priceCurrency'] ?? 'USD');
            }
        }

        $image = $obj['image'] ?? null;
        if ($image === null && is_array($offers)) {
            $image = $offers['image'] ?? (isset($offers[0]) && is_array($offers[0]) ? ($offers[0]['image'] ?? null) : null);
        }
        $imageUrl = null;
        if (is_string($image) && filter_var($image, FILTER_VALIDATE_URL)) {
            $imageUrl = $image;
        } elseif (is_array($image) && isset($image[0])) {
            $firstImg = is_string($image[0]) ? $image[0] : ($image[0]['url'] ?? null);
            if (is_string($firstImg) && filter_var($firstImg, FILTER_VALIDATE_URL)) {
                $imageUrl = $firstImg;
            }
        }

        return [
            'name' => trim((string) $name),
            'price' => $price,
            'currency' => $currency,
            'image_url' => $imageUrl,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function extractFromMetaTags(string $html): ?array
    {
        $name = $this->metaContent($html, 'og:title', 'property')
            ?? $this->metaContent($html, 'twitter:title', 'name')
            ?? $this->metaTitle($html);
        if ($name === null || trim($name) === '') {
            return null;
        }
        $name = html_entity_decode(trim($name), ENT_QUOTES, 'UTF-8');

        $imageUrl = $this->metaContent($html, 'og:image', 'property')
            ?? $this->metaContent($html, 'twitter:image', 'name');
        if ($imageUrl !== null) {
            $imageUrl = trim($imageUrl);
            if (! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $imageUrl = null;
            }
        }

        $price = 0.0;
        $priceStr = $this->metaContent($html, 'product:price:amount', 'property');
        if ($priceStr !== null) {
            $price = (float) str_replace(',', '', trim($priceStr));
        }
        if ($price === 0.0 && preg_match('/"(?:lowPrice|highPrice|price|priceAmount)"\s*:\s*["\']?([\d.]+)/', $html, $m)) {
            $price = (float) $m[1];
        }
        if ($price === 0.0 && preg_match('/"price":\s*([\d.]+)/', $html, $m)) {
            $price = (float) $m[1];
        }
        if ($price === 0.0 && preg_match('/\$([\d,]+\.?\d*)/', $html, $m)) {
            $price = (float) str_replace(',', '', $m[1]);
        }

        $currency = 'USD';
        $currencyStr = $this->metaContent($html, 'product:price:currency', 'property');
        if ($currencyStr !== null) {
            $currency = trim($currencyStr);
        }

        return [
            'name' => $name,
            'price' => $price,
            'currency' => $currency,
            'image_url' => $imageUrl,
        ];
    }

    /**
     * Extract meta content supporting both attribute orders: attr content / content attr.
     */
    private function metaContent(string $html, string $propOrName, string $attrType): ?string
    {
        $attr = preg_quote($attrType, '/');
        $val = preg_quote($propOrName, '/');
        if (preg_match('/<meta[^>]+' . $attr . '=["\']' . $val . '["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+' . $attr . '=["\']' . $val . '["\']/i', $html, $m)) {
            return $m[1];
        }
        return null;
    }

    private function metaTitle(string $html): ?string
    {
        if (preg_match('/<title>([^<]+)<\/title>/i', $html, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Whether the URL/store typically shows prices in USD (e.g. amazon.com, ebay.com).
     */
    private function expectsUsdFromUrl(string $url, string $storeKey): bool
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $usdDomains = ['amazon.com', 'ebay.com', 'walmart.com', 'etsy.com'];
        foreach ($usdDomains as $d) {
            if (str_contains($host, $d)) {
                return true;
            }
        }
        return in_array(strtolower($storeKey), ['amazon', 'ebay', 'walmart', 'etsy'], true);
    }

    /**
     * Merge meta result with DOM. For Amazon: always run DOM and prefer DOM when stronger.
     * For others: allow DOM to override meta when DOM has stronger price/title/image.
     *
     * @return array{data: array<string, mixed>, source: string}
     */
    private function mergeMetaWithDom(array $metaData, ?array $domData, string $url, string $storeKey): array
    {
        $isAmazon = str_contains(strtolower($url), 'amazon.');
        $merged = $metaData;
        $domImproved = false;

        if ($domData !== null && $this->isValidResult($domData)) {
            $merged = $this->mergeResults($metaData, $domData, $storeKey);
            $domImproved = $merged !== $metaData || $this->isStrongerPrice($domData, $metaData)
                || $this->isCleanerTitle((string) ($domData['name'] ?? ''), (string) ($metaData['name'] ?? ''));
        }

        $source = $domImproved ? 'meta_dom_merged' : 'meta_tags';
        if ($domImproved && $isAmazon && $this->isStrongerPrice($domData, $metaData)) {
            $source = 'dom';
        }

        return ['data' => $merged, 'source' => $source];
    }

    /**
     * Merge override into base. Override fields replace base only when they meaningfully improve.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function mergeResults(array $base, array $override, string $storeKey): array
    {
        $result = $base;

        if ($this->isStrongerPrice($override, $base)) {
            $result['price'] = (float) ($override['price'] ?? 0);
            $result['currency'] = (string) ($override['currency'] ?? 'USD');
        }

        $overrideName = trim((string) ($override['name'] ?? ''));
        $baseName = trim((string) ($base['name'] ?? ''));
        if ($overrideName !== '' && $this->isCleanerTitle($overrideName, $baseName)) {
            $result['name'] = $this->stripProductTitleNoise($overrideName) ?: $overrideName;
        }

        $overrideImage = $override['image_url'] ?? null;
        if ($overrideImage !== null && trim((string) $overrideImage) !== '' && filter_var(trim((string) $overrideImage), FILTER_VALIDATE_URL)) {
            $baseImage = $base['image_url'] ?? null;
            if ($baseImage === null || trim((string) $baseImage) === '') {
                $result['image_url'] = trim((string) $overrideImage);
            } else {
                $overrideImgStr = strtolower((string) $overrideImage);
                if (str_contains($overrideImgStr, 'images-na.ssl-images-amazon.com') || str_contains($overrideImgStr, 'landing') || str_contains($overrideImgStr, 'imgblk')) {
                    $result['image_url'] = trim((string) $overrideImage);
                }
            }
        }

        return $result;
    }

    /**
     * DOM has a stronger valid price when it has price > 0. Prefer DOM for visible product price.
     */
    private function isStrongerPrice(?array $domData, ?array $metaData): bool
    {
        if ($domData === null || ! is_array($domData)) {
            return false;
        }
        $domPrice = (float) ($domData['price'] ?? 0);

        return $domPrice > 0;
    }

    /**
     * Candidate title is cleaner (e.g. DOM H1) than current (e.g. meta page title).
     */
    private function isCleanerTitle(string $candidate, string $current): bool
    {
        $candidate = trim($candidate);
        $current = trim($current);
        if ($candidate === '') {
            return false;
        }
        $cleanCandidate = $this->stripProductTitleNoise($candidate);
        if ($cleanCandidate === '' || mb_strlen($cleanCandidate) < 5) {
            return false;
        }
        $currentHasNoise = preg_match('/^Amazon\.com\s*:/i', $current) === 1
            || preg_match('/\s*:\s*[A-Za-z0-9\s&\-\']+$/u', $current) === 1;
        $candidateHasNoise = preg_match('/^Amazon\.com\s*:/i', $candidate) === 1
            || preg_match('/\s*:\s*[A-Za-z0-9\s&\-\']+$/u', $candidate) === 1;
        if ($currentHasNoise && ! $candidateHasNoise) {
            return true;
        }
        $cleanCurrent = $this->stripProductTitleNoise($current);
        if ($cleanCurrent !== '' && $cleanCandidate !== $cleanCurrent && mb_strlen($cleanCandidate) <= mb_strlen($cleanCurrent) + 20) {
            return true;
        }

        return false;
    }

    /**
     * Strip Amazon.com: prefix and generic category suffixes like ": Cell Phones & Accessories".
     */
    private function stripProductTitleNoise(string $title): string
    {
        $title = html_entity_decode(trim($title), ENT_QUOTES, 'UTF-8');
        $title = preg_replace('/^Amazon\.com\s*:\s*/i', '', $title);
        $title = preg_replace('/^Amazon\s*:\s*/i', '', $title);
        $title = preg_replace('/\s*:\s*[A-Za-z0-9\s&\-\']+$/u', '', $title);
        return trim($title);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function extractFromDom(string $html, string $storeKey = 'unknown'): ?array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $crawler = new Crawler($html);
            $name = null;
            $h1 = $crawler->filter('h1')->first();
            if ($h1->count() > 0) {
                $name = trim($h1->text());
            }
            if (($name === null || $name === '') && $crawler->filter('[class*="product-title"], [id*="product-title"], [class*="productTitle"], [data-product-title]')->count() > 0) {
                $el = $crawler->filter('[class*="product-title"], [id*="product-title"], [class*="productTitle"], [data-product-title]')->first();
                $name = trim($el->text());
            }
            if ($name === null || trim($name) === '') {
                return null;
            }

            $price = $this->extractMainProductPriceFromDom($crawler, $storeKey);

            $imageUrl = $this->extractProductImageFromDom($crawler, $storeKey);
            if ($imageUrl === null && $crawler->filter('[class*="product"] img, [id*="product"] img, main img, [data-product] img')->count() > 0) {
                $img = $crawler->filter('[class*="product"] img, [id*="product"] img, main img, [data-product] img')->first();
                $src = $img->attr('src') ?? $img->attr('data-src');
                if ($src !== null && $src !== '') {
                    $src = $this->normalizeImageUrl($src);
                    if (filter_var($src, FILTER_VALIDATE_URL)) {
                        $imageUrl = $src;
                    }
                }
            }
            if ($imageUrl === null && $crawler->filter('img')->count() > 0) {
                $img = $crawler->filter('img')->first();
                $src = $img->attr('src') ?? $img->attr('data-src');
                if ($src !== null && $src !== '') {
                    $src = $this->normalizeImageUrl($src);
                    if (filter_var($src, FILTER_VALIDATE_URL)) {
                        $imageUrl = $src;
                    }
                }
            }

            return [
                'name' => trim($name),
                'price' => $price,
                'currency' => 'USD',
                'image_url' => $imageUrl,
            ];
        } catch (\Throwable) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Extract main product image URL with store-specific selectors.
     */
    private function extractProductImageFromDom(Crawler $crawler, string $storeKey): ?string
    {
        $selectors = match (strtolower($storeKey)) {
            'amazon' => ['#landingImage', '#imgBlkFront', '[data-a-image-name="landingImage"]', 'img[data-old-hires]'],
            'ebay' => ['#icImg', '#vi_main_img_fs', '.img.img500', '[itemprop="image"]', 'img[data-testid="product-image"]'],
            'walmart' => ['[data-automation-id="product-image"]', '.prod-hero-image img', '[itemprop="image"]'],
            'etsy' => ['#listing-page-image', '.wt-max-width-full', '[data-buy-box-listing-image] img'],
            default => [],
        };

        foreach ($selectors as $sel) {
            try {
                if ($crawler->filter($sel)->count() === 0) {
                    continue;
                }
                $img = $crawler->filter($sel)->first();
                $src = $img->attr('src') ?? $img->attr('data-old-hires') ?? $img->attr('data-src') ?? $img->attr('data-zoom-src') ?? $img->attr('data-lazy-src');
                if ($src !== null && $src !== '') {
                    $src = $this->normalizeImageUrl($src);
                    if (filter_var($src, FILTER_VALIDATE_URL)) {
                        return $src;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }
        return null;
    }

    private function normalizeImageUrl(string $src): string
    {
        $src = trim($src);
        if (! str_starts_with($src, 'http')) {
            $src = str_starts_with($src, '//') ? 'https:' . $src : $src;
        }
        return $src;
    }

    /**
     * Extract main product price, avoiding import charges, shipping, tax, totals.
     * For Amazon: collects candidates, scores them, returns best. For others: original logic.
     */
    private function extractMainProductPriceFromDom(Crawler $crawler, string $storeKey = 'unknown'): float
    {
        $storeKey = strtolower($storeKey);
        if ($storeKey === 'amazon') {
            $candidates = $this->collectAmazonPriceCandidates($crawler);
            $best = $this->pickBestAmazonPriceCandidate($candidates);

            return $best !== null ? (float) $best['price'] : 0.0;
        }

        $excludeKeywords = ['import', 'shipping', 'delivery', 'tax', 'total', 'charges', 'duties'];
        $storeSelectors = match ($storeKey) {
            'ebay' => ['#prcIsum', '.notranslate', '[itemprop="price"]', '.u-flL.condText', '.notranslate.mm-price'],
            'walmart' => ['[itemprop="price"]', '.price-current', '[data-automation-id="product-price"]', '.prod-PriceHero .price'],
            'etsy' => ['.wt-text-title-larger', '[data-buy-box-region] .wt-text-title-larger', '.wt-text-title-03'],
            default => [],
        };

        foreach ($storeSelectors as $sel) {
            try {
                if ($crawler->filter($sel)->count() === 0) {
                    continue;
                }
                $el = $crawler->filter($sel)->first();
                $text = trim($el->text());
                $content = $el->attr('content') ?? $text;
                $parentText = $this->getAncestorText($el, 2);
                $combined = strtolower($text . ' ' . $content . ' ' . $parentText);
                if ($this->containsAny($combined, $excludeKeywords)) {
                    continue;
                }
                $parseText = $content !== '' ? $content : $text;
                if (preg_match('/\$?€?£?([\d,]+\.?\d*)/', $parseText, $m)) {
                    $val = (float) str_replace(',', '', $m[1]);
                    if ($val > 0 && $val < 100000) {
                        return $val;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $fallbackSelectors = [
            '[class*="a-price"]:not([class*="shipping"]):not([class*="import"])',
            '[class*="product-price"]',
            '[class*="sale-price"]',
            '[class*="price"]',
        ];

        foreach ($fallbackSelectors as $sel) {
            try {
                if ($crawler->filter($sel)->count() === 0) {
                    continue;
                }
                $el = $crawler->filter($sel)->first();
                $text = trim($el->text());
                $parentText = $this->getAncestorText($el, 2);
                $combined = strtolower($text . ' ' . $parentText);
                if ($this->containsAny($combined, $excludeKeywords)) {
                    continue;
                }
                if (preg_match('/\$?([\d,]+\.?\d*)/', $text, $m)) {
                    $val = (float) str_replace(',', '', $m[1]);
                    if ($val > 0 && $val < 100000) {
                        return $val;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return 0.0;
    }

    /**
     * Amazon-only: collect price candidates from high-priority buy-box / main pricing areas.
     *
     * @return array<int, array{price: float, text: string, selector: string, ancestor_text: string, source: string}>
     */
    private function collectAmazonPriceCandidates(Crawler $crawler): array
    {
        $candidates = [];

        $selectorConfigs = [
            ['selector' => '#corePrice_feature_div .a-offscreen', 'source' => 'corePrice_a-offscreen', 'priority' => 100],
            ['selector' => '#corePriceDisplay_desktop_feature_div .a-offscreen', 'source' => 'corePriceDisplay_a-offscreen', 'priority' => 95],
            ['selector' => '.priceToPay .a-offscreen', 'source' => 'priceToPay_a-offscreen', 'priority' => 90],
            ['selector' => '#apex_desktop .a-offscreen', 'source' => 'apex_desktop_a-offscreen', 'priority' => 85],
            ['selector' => '#corePrice_feature_div .a-price-whole', 'source' => 'corePrice_whole', 'priority' => 80],
            ['selector' => '#corePriceDisplay_desktop_feature_div .a-price-whole', 'source' => 'corePriceDisplay_whole', 'priority' => 75],
            ['selector' => '.priceToPay .a-price-whole', 'source' => 'priceToPay_whole', 'priority' => 70],
            ['selector' => '.a-price.a-price--primary .a-offscreen', 'source' => 'primary_a-offscreen', 'priority' => 65],
            ['selector' => '[data-cel-widget*="corePrice"] .a-offscreen', 'source' => 'corePrice_widget_a-offscreen', 'priority' => 60],
            ['selector' => '#corePrice_feature_div', 'source' => 'corePrice_div', 'priority' => 50],
            ['selector' => '#corePriceDisplay_desktop_feature_div', 'source' => 'corePriceDisplay_div', 'priority' => 45],
            ['selector' => '.a-price.a-price--primary .a-price-whole', 'source' => 'primary_whole', 'priority' => 40],
            ['selector' => '[data-cel-widget*="corePrice"] .a-price-whole', 'source' => 'corePrice_widget_whole', 'priority' => 35],
            ['selector' => '.a-price .a-offscreen', 'source' => 'a-price_a-offscreen', 'priority' => 20],
            ['selector' => '.a-price-whole', 'source' => 'a-price-whole', 'priority' => 10],
        ];

        $excludeKeywords = [
            'shipping', 'delivery', 'import', 'import charges', 'tax', 'total', 'fees',
            'coupon', 'savings', 'save', 'list price', 'was', 'typical price', 'lowest price',
            'protection plan', 'trade-in', 'charges', 'duties',
        ];

        foreach ($selectorConfigs as $config) {
            $sel = $config['selector'];
            $source = $config['source'];
            $basePriority = $config['priority'];
            try {
                if ($crawler->filter($sel)->count() === 0) {
                    continue;
                }
                $nodes = $crawler->filter($sel);
                foreach ($nodes as $i => $node) {
                    $sub = new Crawler($node);
                    $text = trim($sub->text());
                    $content = $sub->attr('content') ?? $text;
                    $parseText = $content !== '' ? $content : $text;
                    $ancestorText = $this->getAncestorText($sub, 4);
                    $combined = strtolower($text . ' ' . $content . ' ' . $ancestorText);

                    if ($this->containsAny($combined, $excludeKeywords)) {
                        continue;
                    }

                    if (preg_match('/\$?€?£?([\d,]+\.?\d*)/', $parseText, $m)) {
                        $val = (float) str_replace(',', '', $m[1]);
                        if ($val <= 0 || $val >= 100000) {
                            continue;
                        }

                        $candidates[] = [
                            'price' => $val,
                            'text' => $parseText,
                            'selector' => $sel,
                            'ancestor_text' => $ancestorText,
                            'source' => $source,
                            'base_priority' => $basePriority,
                        ];
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $candidates;
    }

    /**
     * Score an Amazon price candidate. Higher = more likely to be the main visible product price.
     */
    private function scoreAmazonPriceCandidate(array $candidate): int
    {
        $score = $candidate['base_priority'] ?? 0;

        $ancestor = strtolower($candidate['ancestor_text'] ?? '');
        $penaltyKeywords = [
            'list price' => -80,
            'was' => -70,
            'typical price' => -70,
            'lowest price' => -60,
            'list' => -50,
            'strikethrough' => -60,
            'crossed' => -50,
        ];
        foreach ($penaltyKeywords as $kw => $penalty) {
            if (str_contains($ancestor, $kw)) {
                $score += $penalty;
            }
        }

        if (str_contains($candidate['source'] ?? '', 'a-offscreen') && ! str_contains($ancestor, 'list')) {
            $score += 15;
        }

        if (str_contains($candidate['source'] ?? '', 'corePrice') || str_contains($candidate['source'] ?? '', 'priceToPay')) {
            $score += 10;
        }

        $price = (float) ($candidate['price'] ?? 0);
        $text = (string) ($candidate['text'] ?? '');
        if ($price >= 1 && $price <= 10000 && preg_match('/\d+\.\d{2}/', $text)) {
            $score += 5;
        }
        if ($price > 0 && $price < 1) {
            $score -= 90;
        }

        return max(0, $score);
    }

    /**
     * Pick the best Amazon price candidate by score. Prefer main visible price over list/variant.
     *
     * @param  array<int, array{price: float, text: string, selector: string, ancestor_text: string, source: string, base_priority?: int}>  $candidates
     * @return array{price: float, text: string, selector: string, source: string}|null
     */
    private function pickBestAmazonPriceCandidate(array $candidates): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $scored = [];
        foreach ($candidates as $c) {
            $c['score'] = $this->scoreAmazonPriceCandidate($c);
            $scored[] = $c;
        }

        usort($scored, static function ($a, $b) {
            return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
        });

        $best = $scored[0];
        if (($best['score'] ?? 0) < 5) {
            return null;
        }

        return [
            'price' => $best['price'],
            'text' => $best['text'],
            'selector' => $best['selector'],
            'source' => $best['source'],
        ];
    }

    private function getAncestorText(Crawler $crawler, int $levels): string
    {
        try {
            $node = $crawler->getNode(0);
            if ($node === null) {
                return '';
            }
            $text = '';
            for ($i = 0; $i < $levels && $node !== null; $i++) {
                $node = $node->parentNode;
                if ($node === null) {
                    break;
                }
                $text .= ' ' . trim($node->textContent ?? '');
            }
            return $text;
        } catch (\Throwable) {
            return '';
        }
    }

    private function containsAny(string $haystack, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($haystack, $kw)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function extractWithOpenAI(string $html, string $url, string $storeKey): ?array
    {
        if (! config('openai.api_key')) {
            return null;
        }
        $payload = $this->buildReducedPayload($html, $url);
        return $this->extractFromReducedPayload($payload, $url, $storeKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildReducedPayload(string $html, string $url): array
    {
        $title = null;
        if (preg_match('/<title>([^<]+)<\/title>/i', $html, $m)) {
            $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }
        if ($title === null && preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }

        $meta = [];
        foreach (['og:title' => 'property', 'og:image' => 'property', 'product:price:amount' => 'property', 'product:price:currency' => 'property', 'twitter:title' => 'name', 'twitter:image' => 'name'] as $prop => $attr) {
            $val = $this->metaContent($html, $prop, $attr);
            if ($val !== null) {
                $meta[$prop] = trim($val);
            }
        }

        $jsonLd = [];
        if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches)) {
            foreach ($matches[1] as $jsonStr) {
                $decoded = json_decode(trim($jsonStr), true);
                if (is_array($decoded)) {
                    $jsonLd[] = $decoded;
                }
            }
        }

        $candidateBlocks = $this->extractCandidateBlocks($html);

        return [
            'url' => $url,
            'title' => $title ?? '',
            'meta' => $meta,
            'json_ld' => $jsonLd,
            'candidate_blocks' => $candidateBlocks,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function extractCandidateBlocks(string $html): array
    {
        $blocks = [];
        $patterns = [
            '/<div[^>]*(?:class|id)=["\'][^"\']*(?:product|main|detail)[^"\']*["\'][^>]*>.*?<\/div>/si',
            '/<section[^>]*(?:class|id)=["\'][^"\']*(?:product|main)[^"\']*["\'][^>]*>.*?<\/section>/si',
            '/<article[^>]*>.*?<\/article>/si',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $m)) {
                foreach ($m[0] as $block) {
                    $block = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $block);
                    $block = preg_replace('/<style\b[^>]*>.*?<\/style>/si', '', $block);
                    if (mb_strlen($block) > 100 && mb_strlen($block) < 5000) {
                        $blocks[] = mb_substr($block, 0, 2000);
                    }
                }
            }
        }
        return array_slice(array_unique($blocks), 0, 5);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function extractFromReducedPayload(array $payload, string $url, string $storeKey): ?array
    {
        if (! config('openai.api_key')) {
            return null;
        }
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $systemMessage = 'You extract product data. Return ONLY valid JSON with keys: name, price, currency, image_url. No markdown.';
        $userMessage = "Extract product data from this payload. Return JSON with name, price (BASE product price ONLY - not shipping, import charges, or total), currency, image_url (string or null):\n\n" . $jsonPayload;

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $systemMessage],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'temperature' => 0,
            ]);
            $content = $response->choices[0]->message->content ?? null;
            if (! is_string($content) || trim($content) === '') {
                return null;
            }
            $content = $this->stripMarkdownCodeBlock($content);
            $data = json_decode($content, true);
            if (! is_array($data) || ! isset($data['name'])) {
                return null;
            }
            return [
                'name' => (string) $data['name'],
                'price' => (float) ($data['price'] ?? 0),
                'currency' => (string) ($data['currency'] ?? 'USD'),
                'image_url' => isset($data['image_url']) && $data['image_url'] !== null ? (string) $data['image_url'] : null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Regex fallback (legacy extractFromHtmlPlaceholder).
     *
     * @return array<string, mixed>
     */
    public function extractFromRegex(string $html): array
    {
        $title = $this->metaContent($html, 'og:title', 'property')
            ?? $this->metaTitle($html)
            ?? 'Product';
        $title = html_entity_decode(trim($title), ENT_QUOTES, 'UTF-8');

        $imageUrl = $this->metaContent($html, 'og:image', 'property')
            ?? $this->metaContent($html, 'twitter:image', 'name');
        if ($imageUrl !== null && ! filter_var(trim($imageUrl), FILTER_VALIDATE_URL)) {
            $imageUrl = null;
        } elseif ($imageUrl !== null) {
            $imageUrl = trim($imageUrl);
        }

        $price = 0.0;
        if (preg_match('/"(?:lowPrice|highPrice|price|priceAmount)"\s*:\s*["\']?([\d.]+)/', $html, $m)) {
            $price = (float) $m[1];
        }
        if ($price === 0.0 && preg_match('/"price"\s*:\s*["\']?([\d.]+)/', $html, $m)) {
            $price = (float) $m[1];
        }
        if ($price === 0.0 && preg_match('/\$([\d,]+\.?\d*)/', $html, $m)) {
            $price = (float) str_replace(',', '', $m[1]);
        }

        return [
            'name' => $title,
            'price' => $price,
            'currency' => 'USD',
            'image_url' => $imageUrl,
        ];
    }

    private function stripMarkdownCodeBlock(string $content): string
    {
        $content = trim($content);
        if (str_starts_with($content, '```json')) {
            $content = substr($content, 7);
        } elseif (str_starts_with($content, '```')) {
            $content = substr($content, 3);
        }
        if (str_ends_with($content, '```')) {
            $content = substr($content, 0, -3);
        }
        return trim($content);
    }

    private function getCountryForStore(string $storeKey): string
    {
        return match ($storeKey) {
            'amazon', 'ebay', 'walmart', 'etsy' => 'USA',
            'aliexpress' => 'China',
            'trendyol' => 'Turkey',
            default => 'Unknown',
        };
    }
}
