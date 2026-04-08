<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Merge uploaded receipt images with optional legacy URL lines for warehouse_receipts.images (JSON array).
 */
class AdminWarehouseReceiptImages
{
    /**
     * @return list<string>
     */
    public static function collectFromRequest(Request $request): array
    {
        $urls = [];

        if ($request->hasFile('receipt_images')) {
            foreach ($request->file('receipt_images', []) as $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('warehouse-receipts', 'public');
                    $urls[] = Storage::disk('public')->url($path);
                }
            }
        }

        if ($request->filled('images_text')) {
            $lines = preg_split('/\r\n|\r|\n/', (string) $request->input('images_text', ''));
            foreach (array_filter(array_map('trim', $lines)) as $line) {
                $urls[] = $line;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Normalize a stored entry to a usable img src (http, https, or /storage/...).
     */
    public static function displayUrl(string $entry): string
    {
        $entry = trim($entry);
        if ($entry === '') {
            return '';
        }
        if (str_starts_with($entry, 'http://') || str_starts_with($entry, 'https://')) {
            return $entry;
        }
        if (str_starts_with($entry, '/')) {
            return url($entry);
        }

        return Storage::disk('public')->url($entry);
    }
}
