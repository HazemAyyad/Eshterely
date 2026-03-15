# Shipping Quote Integration with Product Import

This document describes how the Shipping Engine is integrated into the existing product import/extraction pipeline for Eshterely.

## Where Shipping Quote Was Integrated

- **Controller**: `App\Http\Controllers\Api\ProductImportController::importFromUrl()`
- **Integration point**: After product data is successfully extracted and normalized, and after variations are built from `scraperapi_raw`, the controller calls `ProductImportShippingQuoteService::quoteFromProduct()` with the **same** `$product` array that was returned by the extraction pipeline. The returned quote (or `null`) is attached as `product['shipping_quote']` and the full payload is returned as the API response.

No changes were made to:

- `ProductPageFetcherService` (fetch strategy, including ScraperAPI)
- `ProductExtractionService` (strategy selection, JSON-LD / meta / DOM / OpenAI / regex, or structured APIs)
- `StructuredProductImportService` (ScraperAPI structured/rendered endpoints)

The pipeline remains: **URL → fetch HTML (or structured API) → extract → normalize → [optional variations] → [new] shipping quote from normalized data → response.**

## How the Implementation Preserves ScraperAPI and Other Extraction Sources

- Shipping quote calculation is **source-agnostic**. It consumes the **normalized product shape** (e.g. `name`, `price`, `currency`, `weight`, `weight_unit`, `length`, `width`, `height`, `dimension_unit`, `quantity`) that can be produced by:
  - ScraperAPI structured APIs (Amazon, eBay, Walmart, AliExpress rendered)
  - HTML pipeline (JSON-LD, meta, DOM, OpenAI, regex)
  - Any future extractor that fills the same normalized fields

- There is no branch on `fetch_source` or `extraction_source` for shipping. `ProductToShippingInputMapper` reads only these normalized fields; it does not depend on where they came from.

- The import endpoint does not replace or bypass any extraction path. All existing strategies and fallbacks continue to run unchanged.

## How Estimated Quotes Work When Product Shipping Fields Are Incomplete

- **Sufficient data for a quote**: The mapper considers data sufficient if either **weight > 0** or **all of length, width, height > 0**. When sufficient, a quote is always attempted (missing values are filled with safe fallbacks).

- **Fallback defaults** (used only when a field is missing or zero):
  - Weight: `0.5` kg
  - Dimensions: `10 × 10 × 10` cm
  - Units are normalized to kg and cm by the existing `PackageNormalizer`.

- **Estimated flag**: If any of `weight`, `length`, `width`, or `height` were missing and fallbacks were used, the response includes `shipping_quote.estimated === true` and `shipping_quote.missing_fields` listing those fields. A note is added to `shipping_quote.notes` explaining the estimate.

- **Insufficient data**: If there is no weight and no dimensions (all zero/missing), the service does **not** call `ShippingQuoteService::quote()`. The response includes `shipping_quote: null`, and the product preview is unchanged. No exception is thrown; the import is still successful.

- **Quote calculation failure**: If `ShippingQuoteService::quote()` throws, the failure is caught inside `ProductImportShippingQuoteService`, logged, and the controller receives `null` and sets `shipping_quote: null`. The product preview is still returned.

## API Response Shape (Backward Compatible)

The import response remains a single JSON object with all existing product keys (e.g. `name`, `price`, `currency`, `image_url`, `store_key`, `canonical_url`, `extraction_source`, `variations`, etc.). One key is **added**:

- **`shipping_quote`**: `null` or an object with:
  - `carrier`, `warehouse_mode`, `actual_weight`, `volumetric_weight`, `chargeable_weight`, `currency`, `amount`
  - `estimated` (boolean), `missing_fields` (array of strings), `notes` (array of strings)

No existing keys were removed or renamed.

Optional request parameters for the import endpoint:

- `destination_country` (optional): Used as shipping destination for the quote (e.g. `US`, `SA`). Defaults to `config('services.shipping.default_destination_country', 'US')` (configurable via `SHIPPING_DEFAULT_DESTINATION_COUNTRY`).
- `warehouse_mode` (optional): Boolean passed to the shipping quote.
- `quantity` (optional): Quantity for the quote (default 1).

## Components Added

| Component | Purpose |
|-----------|---------|
| `ProductToShippingInputMapper` | Maps normalized product array to `ShippingQuoteService` input; applies fallbacks and tracks `missing_fields` and `estimated`. |
| `ProductImportShippingQuoteService` | Orchestrates mapper + `ShippingQuoteService::quote()`; returns null on insufficient data or on exception; logs failures and use of fallbacks. |
| Config `services.shipping.default_destination_country` | Default destination country when not provided in the request (env: `SHIPPING_DEFAULT_DESTINATION_COUNTRY`). |

Pricing and behaviour (currency, min charge, warehouse fee, volumetric divisor, etc.) continue to come from **admin-configurable** `ShippingPricingConfigService` / `ShippingSetting`; no hardcoded business values were added in this integration.

## What the Next Task Should Implement

1. **Carrier-specific pricing** – Use real rate cards or zone-based pricing for DHL, UPS, FedEx (and any other carriers) instead of the current minimal formula (min charge + warehouse fee + markup/multi-package). The existing `ShippingQuoteService` and config keys (e.g. carrier discounts) are prepared for this.

2. **Confirm-product / checkout flow** – When the user confirms a product (e.g. adds to cart or proceeds to checkout), persist or recalculate the shipping quote using the same engine and, if needed, carrier selection and destination from the user profile or address.

3. **Optional enhancements** – Apply rounding strategy to chargeable weight in the quote output; apply carrier discount percentages per carrier; support multiple packages explicitly in the quote structure and API.
