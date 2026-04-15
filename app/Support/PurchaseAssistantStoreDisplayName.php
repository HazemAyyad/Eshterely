<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Human-friendly store label from hostname (e.g. shein.com → SHEIN, amazon.com → Amazon).
 */
final class PurchaseAssistantStoreDisplayName
{
    public static function fromHost(?string $host): string
    {
        if ($host === null || $host === '') {
            return 'Store';
        }

        $host = strtolower(trim($host));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $parts = explode('.', $host);
        $base = $parts[0] ?? $host;

        /** @var array<string, string> */
        static $map = [
            'shein' => 'SHEIN',
            'amazon' => 'Amazon',
            'ebay' => 'eBay',
            'walmart' => 'Walmart',
            'aliexpress' => 'AliExpress',
            'temu' => 'Temu',
            'hm' => 'H&M',
            'zara' => 'Zara',
            'nike' => 'Nike',
            'adidas' => 'Adidas',
            'target' => 'Target',
            'bestbuy' => 'Best Buy',
        ];

        $key = strtolower($base);
        if (isset($map[$key])) {
            return $map[$key];
        }

        return Str::title(str_replace(['-', '_'], ' ', $base));
    }

    public static function fromSourceUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return self::fromHost(is_string($host) ? $host : null);
    }
}
