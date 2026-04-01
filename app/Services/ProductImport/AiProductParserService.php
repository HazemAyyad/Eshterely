<?php

namespace App\Services\ProductImport;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class AiProductParserService
{
    /**
     * AI = parser only.
     * Input = trimmed reduced payload (no full HTML).
     * Output = strict JSON with allowed keys only.
     *
     * Allowed keys: title, price, image, variants, description
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function parse(array $payload): ?array
    {
        if (! config('openai.api_key')) {
            return null;
        }

        $trimmed = $this->trimPayload($payload);
        $jsonPayload = json_encode($trimmed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $system = implode("\n", [
            'You are a strict JSON parser for e-commerce product pages.',
            'Return ONLY valid minified JSON. No markdown, no prose.',
            'Allowed keys: title, price, image, variants, description.',
            'price must be a number or null.',
            'image must be a string URL or null.',
            'variants must be an array of objects: {type: string, options: string[]} or null.',
            'description must be string or null.',
            'Measurements (weight/dimensions) are NOT allowed. If not explicitly present in the payload, do not invent anything.',
        ]);

        $user = "Parse product info from this payload and return strict JSON:\n" . ($jsonPayload ?? '{}');

        try {
            $res = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'temperature' => 0,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

            $content = $res->choices[0]->message->content ?? null;
            if (! is_string($content) || trim($content) === '') {
                return null;
            }

            $content = $this->stripMarkdownCodeBlock($content);
            $data = json_decode($content, true);
            if (! is_array($data)) {
                return null;
            }

            return $this->sanitizeOutput($data);
        } catch (\Throwable $e) {
            Log::warning('AiProductParserService failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function trimPayload(array $payload): array
    {
        $out = $payload;

        // Keep only the smallest, most relevant fields.
        $allowedTop = ['url', 'title', 'meta', 'json_ld', 'candidate_blocks'];
        $out = array_intersect_key($out, array_flip($allowedTop));

        if (isset($out['candidate_blocks']) && is_array($out['candidate_blocks'])) {
            $out['candidate_blocks'] = array_slice(array_values(array_filter(array_map(function ($b) {
                if (! is_string($b)) {
                    return null;
                }
                $b = trim($b);
                if ($b === '') {
                    return null;
                }
                return mb_substr($b, 0, 1200);
            }, $out['candidate_blocks']))), 0, 5);
        }

        if (isset($out['json_ld']) && is_array($out['json_ld'])) {
            $out['json_ld'] = array_slice($out['json_ld'], 0, 3);
        }

        if (isset($out['meta']) && is_array($out['meta'])) {
            // Trim meta values.
            $meta = [];
            foreach ($out['meta'] as $k => $v) {
                if (! is_string($k)) {
                    continue;
                }
                if (is_string($v)) {
                    $meta[$k] = mb_substr(trim($v), 0, 500);
                }
            }
            $out['meta'] = $meta;
        }

        if (isset($out['title']) && is_string($out['title'])) {
            $out['title'] = mb_substr(trim($out['title']), 0, 200);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeOutput(array $data): array
    {
        $allowed = ['title', 'price', 'image', 'variants', 'description'];
        $out = array_intersect_key($data, array_flip($allowed));

        if (array_key_exists('price', $out)) {
            $p = $out['price'];
            $out['price'] = is_numeric($p) ? (float) $p : null;
        } else {
            $out['price'] = null;
        }

        if (array_key_exists('image', $out)) {
            $img = $out['image'];
            $out['image'] = is_string($img) && filter_var($img, FILTER_VALIDATE_URL) ? $img : null;
        } else {
            $out['image'] = null;
        }

        if (array_key_exists('title', $out)) {
            $t = $out['title'];
            $out['title'] = is_string($t) ? trim($t) : null;
        } else {
            $out['title'] = null;
        }

        if (array_key_exists('description', $out)) {
            $d = $out['description'];
            $out['description'] = is_string($d) ? trim($d) : null;
        } else {
            $out['description'] = null;
        }

        if (array_key_exists('variants', $out)) {
            $v = $out['variants'];
            if (! is_array($v) || $v === []) {
                $out['variants'] = null;
            } else {
                $variants = [];
                foreach ($v as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $type = isset($item['type']) ? trim((string) $item['type']) : '';
                    $opts = $item['options'] ?? null;
                    if ($type === '' || ! is_array($opts) || $opts === []) {
                        continue;
                    }
                    $options = [];
                    foreach ($opts as $opt) {
                        $s = trim((string) $opt);
                        if ($s !== '') {
                            $options[] = $s;
                        }
                    }
                    $options = array_values(array_unique($options));
                    if ($options !== []) {
                        $variants[] = ['type' => $type, 'options' => $options];
                    }
                }
                $out['variants'] = $variants !== [] ? $variants : null;
            }
        } else {
            $out['variants'] = null;
        }

        return $out;
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
}

