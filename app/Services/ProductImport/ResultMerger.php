<?php

namespace App\Services\ProductImport;

/**
 * Safe merger: never overwrite good data with null/empty.
 * "Good" = non-null and non-empty string/array, or numeric > 0, or boolean.
 */
class ResultMerger
{
    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @param  array<int, string>  $keys  Optional allowlist. Empty = merge all keys from override.
     * @return array<string, mixed>
     */
    public function merge(array $base, array $override, array $keys = []): array
    {
        $out = $base;
        $mergeKeys = $keys !== [] ? $keys : array_keys($override);

        foreach ($mergeKeys as $key) {
            if (! array_key_exists($key, $override)) {
                continue;
            }
            $incoming = $override[$key];
            if (! $this->isMeaningful($incoming)) {
                continue;
            }

            if (! array_key_exists($key, $out) || ! $this->isMeaningful($out[$key])) {
                $out[$key] = $incoming;
                continue;
            }

            // Prefer stronger numerics (e.g. price) when base is zero.
            if (is_numeric($incoming) && is_numeric($out[$key])) {
                $in = (float) $incoming;
                $cur = (float) $out[$key];
                if ($cur <= 0 && $in > 0) {
                    $out[$key] = $incoming;
                }
                continue;
            }

            // For nested arrays (e.g. dimensions), merge recursively.
            if (is_array($incoming) && is_array($out[$key])) {
                $out[$key] = $this->merge($out[$key], $incoming);
            }
        }

        return $out;
    }

    private function isMeaningful(mixed $v): bool
    {
        if ($v === null) {
            return false;
        }
        if (is_string($v)) {
            return trim($v) !== '';
        }
        if (is_numeric($v)) {
            return (float) $v > 0;
        }
        if (is_array($v)) {
            return $v !== [];
        }
        if (is_bool($v)) {
            return true;
        }

        return true;
    }
}

