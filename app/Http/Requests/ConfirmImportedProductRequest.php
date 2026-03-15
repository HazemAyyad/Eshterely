<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmImportedProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * Validation rules for confirm payload. Mirrors import-from-url response shape.
     * We snapshot what the user saw; no server-side recalculation.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source_url' => ['required', 'string', 'url'],
            'canonical_url' => ['nullable', 'string', 'url'], // alias, prefer source_url
            'name' => ['required', 'string', 'max:1000'],
            'title' => ['nullable', 'string', 'max:1000'], // alias for name
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'store_key' => ['nullable', 'string', 'max:50'],
            'store_name' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:50'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'weight_unit' => ['nullable', 'string', 'max:10'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'dimension_unit' => ['nullable', 'string', 'max:10'],
            'shipping_quote' => ['required', 'array'],
            'shipping_quote.amount' => ['required', 'numeric', 'min:0'],
            'shipping_quote.currency' => ['nullable', 'string', 'max:10'],
            'shipping_quote.carrier' => ['nullable', 'string', 'max:50'],
            'shipping_quote.pricing_mode' => ['nullable', 'string', 'max:50'],
            'shipping_quote.estimated' => ['nullable', 'boolean'],
            'shipping_quote.missing_fields' => ['nullable', 'array'],
            'shipping_quote.missing_fields.*' => ['string', 'max:50'],
            'shipping_quote.notes' => ['nullable', 'array'],
            'final_pricing' => ['required', 'array'],
            'final_pricing.product_price' => ['nullable', 'numeric', 'min:0'],
            'final_pricing.final_total' => ['required', 'numeric', 'min:0'],
            'final_pricing.estimated' => ['nullable', 'boolean'],
            'final_pricing.notes' => ['nullable', 'array'],
            'extraction_source' => ['nullable', 'string', 'max:100'],
            // Trust model preparation: optional preview reference for future server-side verification
            'preview_token' => ['nullable', 'string', 'max:255'],
            'preview_id' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Get the source URL (canonical_url or source_url).
     */
    public function getSourceUrl(): string
    {
        return $this->input('source_url') ?: $this->input('canonical_url', '');
    }

    /**
     * Get the product title (name or title).
     */
    public function getTitle(): string
    {
        $name = $this->input('name');
        if ($name !== null && trim((string) $name) !== '') {
            return trim((string) $name);
        }
        return trim((string) $this->input('title', ''));
    }

    /**
     * Get normalized attributes for snapshot creation.
     *
     * @return array<string, mixed>
     */
    public function getSnapshotAttributes(): array
    {
        $sourceUrl = $this->getSourceUrl();
        $title = $this->getTitle();
        $price = (float) $this->input('price');
        $currency = strtoupper(trim((string) ($this->input('currency') ?? 'USD')));
        if ($currency === '') {
            $currency = 'USD';
        }
        $quantity = (int) $this->input('quantity', 1);
        $quantity = $quantity < 1 ? 1 : $quantity;

        $packageInfo = array_filter([
            'quantity' => $quantity,
            'weight' => $this->input('weight'),
            'weight_unit' => $this->input('weight_unit'),
            'length' => $this->input('length'),
            'width' => $this->input('width'),
            'height' => $this->input('height'),
            'dimension_unit' => $this->input('dimension_unit'),
        ], fn ($v) => $v !== null && $v !== '');

        $shippingQuote = $this->input('shipping_quote', []);
        $finalPricing = $this->input('final_pricing', []);

        return [
            'source_url' => $sourceUrl,
            'store_key' => $this->input('store_key'),
            'store_name' => $this->input('store_name'),
            'country' => $this->input('country'),
            'title' => $title,
            'image_url' => $this->input('image_url'),
            'product_price' => $price,
            'product_currency' => $currency,
            'package_info' => $packageInfo ?: null,
            'shipping_quote_snapshot' => $shippingQuote,
            'final_pricing_snapshot' => $finalPricing,
            'carrier' => $shippingQuote['carrier'] ?? null,
            'pricing_mode' => $shippingQuote['pricing_mode'] ?? null,
            'estimated' => (bool) ($shippingQuote['estimated'] ?? $finalPricing['estimated'] ?? false),
            'missing_fields' => $shippingQuote['missing_fields'] ?? null,
            'import_metadata' => array_filter([
                'extraction_source' => $this->input('extraction_source'),
                'preview_token' => $this->input('preview_token'),
                'preview_id' => $this->input('preview_id'),
            ], fn ($v) => $v !== null && $v !== ''),
        ];
    }
}
