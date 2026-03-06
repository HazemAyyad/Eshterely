<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Symfony\Component\DomCrawler\Crawler;

class ProductExtractionService
{
    private const MAX_HTML_LENGTH = 15000;

    /**
     * Main entry point. Tries JSON-LD → Meta → DOM → OpenAI → Regex.
     *
     * @return array<string, mixed>
     */
    public function extract(string $html, string $url, string $storeKey): array
    {
        $data = $this->extractFromJsonLd($html);
        if ($this->isValidResult($data)) {
            return $this->normalizeResult($data, $url, $storeKey, 'json_ld');
        }

        $data = $this->extractFromMetaTags($html);
        if ($this->isValidResult($data)) {
            return $this->normalizeResult($data, $url, $storeKey, 'meta_tags');
        }

        $data = $this->extractFromDom($html);
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
     * @return array<string, mixed>|null
     */
    public function extractFromDom(string $html): ?array
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

            $price = $this->extractMainProductPriceFromDom($crawler);

            $imageUrl = null;
            if ($crawler->filter('[class*="product"] img, [id*="product"] img, main img, [data-product] img')->count() > 0) {
                $img = $crawler->filter('[class*="product"] img, [id*="product"] img, main img, [data-product] img')->first();
                $src = $img->attr('src') ?? $img->attr('data-src');
                if ($src && filter_var($src, FILTER_VALIDATE_URL)) {
                    $imageUrl = $src;
                }
            }
            if ($imageUrl === null && $crawler->filter('img')->count() > 0) {
                $img = $crawler->filter('img')->first();
                $src = $img->attr('src') ?? $img->attr('data-src');
                if ($src && filter_var($src, FILTER_VALIDATE_URL)) {
                    $imageUrl = $src;
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
     * Extract main product price, avoiding import charges, shipping, tax, totals.
     */
    private function extractMainProductPriceFromDom(Crawler $crawler): float
    {
        $excludeKeywords = ['import', 'shipping', 'delivery', 'tax', 'total', 'charges', 'duties'];
        $candidates = [];

        $prioritySelectors = [
            '#corePrice_feature_div',
            '#corePriceDisplay_desktop_feature_div',
            '.a-price.a-price--primary',
            '[data-cel-widget*="corePrice"]',
            '.a-price .a-offscreen',
            '.a-price-whole',
        ];

        foreach ($prioritySelectors as $sel) {
            try {
                if ($crawler->filter($sel)->count() === 0) {
                    continue;
                }
                $nodes = $crawler->filter($sel);
                foreach ($nodes as $i => $node) {
                    $sub = new Crawler($node);
                    $text = trim($sub->text());
                    $parentText = $this->getAncestorText($sub, 3);
                    $combined = strtolower($text . ' ' . $parentText);
                    if ($this->containsAny($combined, $excludeKeywords)) {
                        continue;
                    }
                    if (preg_match('/\$?([\d,]+\.?\d*)/', $text, $m)) {
                        $val = (float) str_replace(',', '', $m[1]);
                        if ($val > 0 && $val < 100000) {
                            $candidates[] = $val;
                        }
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if ($candidates !== []) {
            return $candidates[0];
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
