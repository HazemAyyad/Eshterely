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

    public function isValidResult(?array $data): bool
    {
        if ($data === null || ! is_array($data)) {
            return false;
        }
        $name = trim((string) ($data['name'] ?? ''));
        return $name !== '';
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
        $name = null;
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $name = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }
        if ($name === null && preg_match('/<meta[^>]+name=["\']twitter:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $name = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }
        if ($name === null && preg_match('/<title>([^<]+)<\/title>/i', $html, $m)) {
            $name = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }
        if ($name === null || trim($name) === '') {
            return null;
        }

        $imageUrl = null;
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $imageUrl = trim($m[1]);
        }
        if ($imageUrl === null && preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $imageUrl = trim($m[1]);
        }
        if ($imageUrl !== null && ! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $imageUrl = null;
        }

        $price = 0.0;
        if (preg_match('/<meta[^>]+property=["\']product:price:amount["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $price = (float) str_replace(',', '', trim($m[1]));
        }
        if ($price === 0.0 && preg_match('/"price"\s*:\s*["\']?([\d.]+)/', $html, $m)) {
            $price = (float) $m[1];
        }
        if ($price === 0.0 && preg_match('/\$([\d,]+\.?\d*)/', $html, $m)) {
            $price = (float) str_replace(',', '', $m[1]);
        }

        $currency = 'USD';
        if (preg_match('/<meta[^>]+property=["\']product:price:currency["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $currency = trim($m[1]);
        }

        return [
            'name' => trim($name),
            'price' => $price,
            'currency' => $currency,
            'image_url' => $imageUrl,
        ];
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

            $price = 0.0;
            $priceSelectors = [
                '[class*="price"]', '[id*="price"]', '[class*="a-price"]',
                '[class*="product-price"]', '[class*="sale-price"]', '[class*="amount"]',
            ];
            foreach ($priceSelectors as $sel) {
                if ($crawler->filter($sel)->count() > 0) {
                    $text = trim($crawler->filter($sel)->first()->text());
                    if (preg_match('/[\d,]+\.?\d*/', $text, $m)) {
                        $price = (float) str_replace(',', '', $m[0]);
                        if ($price > 0) {
                            break;
                        }
                    }
                }
            }

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
        foreach (['og:title', 'og:image', 'product:price:amount', 'product:price:currency', 'twitter:title', 'twitter:image'] as $prop) {
            if (preg_match('/<meta[^>]+(?:property|name)=["\']' . preg_quote($prop, '/') . '["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
                $meta[$prop] = trim($m[1]);
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
        $systemMessage = 'You extract product data from structured page data. Return ONLY valid JSON with keys: name, price, currency, image_url. No markdown.';
        $userMessage = "Extract product data from this payload. Return JSON with name, price (float, 0 if not found), currency, image_url (string or null):\n\n" . $jsonPayload;

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
