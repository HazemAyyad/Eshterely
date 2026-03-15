# Final Pricing Layer

This document describes the **final pricing breakdown** layer that prepares product + shipping data for the confirm screen, cart insertion, and order creation. It sits on top of the existing import pipeline and shipping quote result.

## Overview

- **Input**: Normalized product data, shipping quote result (from `ProductImportShippingQuoteService`), and quantity.
- **Output**: A complete pricing breakdown (`final_pricing`) included in the product import preview API response.
- **Config**: All business values (service fee, platform markup, minimum order fee, rounding) are admin-configurable via `ShippingPricingConfigService` / `ShippingSetting`; nothing is hardcoded.

## How Final Pricing Is Calculated

1. **Product line**: `product_price` (unit price from product) × `quantity` → line total.
2. **Shipping**: `shipping_amount` and `shipping_currency` come from the shipping quote.
3. **Subtotal**: `subtotal` = line total + shipping amount.
4. **Service fee**: Flat `service_fee` from admin config (e.g. platform fee).
5. **Markup**: `markup_amount` = (line total + shipping) × `platform_markup_percent` / 100.
6. **Minimum order fee**: If `subtotal` < `minimum_order_threshold`, add `minimum_order_fee` (from config).
7. **Final total**: `final_total` = subtotal + service_fee + markup_amount + minimum_order_fee (rounded to 2 decimals).

All of the above use the same currency as the shipping quote (or product currency); the breakdown is returned in a single currency.

## Configurable Values

| Key | Description | Default |
|-----|-------------|---------|
| `service_fee` | Flat fee added to every order | 0 |
| `platform_markup_percent` | Percentage applied to (product total + shipping) | 0 |
| `minimum_order_fee` | Fee added when subtotal is below threshold | 0 |
| `minimum_order_threshold` | Subtotal below this triggers minimum order fee | 0 |
| `rounding_strategy` | Used by shipping engine (e.g. weight rounding) | nearest_kg |

These are stored in `shipping_settings` and editable from the admin shipping settings screen. The final pricing service reads them via `ShippingPricingConfigService`.

## Estimated Shipping and Pricing

If the shipping quote was **estimated** (e.g. fallback weight/dimensions were used because product data was missing):

- `final_pricing.estimated` is set to `true`.
- A note is added to `final_pricing.notes` explaining that pricing is estimated and should be confirmed at checkout.

The UI can use this to show a clear “Estimated total” and prompt the user to confirm at checkout.

## Carrier Auto-Selection (Current Behaviour)

When the client sends `carrier = auto`:

1. The shipping engine obtains quotes from all configured carriers (DHL, UPS, FedEx).
2. A **carrier selection strategy** chooses one result. The default strategy is **cheapest carrier** (`CheapestCarrierSelectionStrategy`).
3. The chosen carrier and amount are used in the shipping quote and then in the final pricing breakdown.

The architecture is prepared for future extensions without changing the pipeline:

- **Recommended carrier**: Implement a strategy that prefers a configured “recommended” carrier when prices are close.
- **ETA-based filtering**: A strategy that filters or ranks by estimated delivery date.
- **Country restrictions**: A strategy that excludes or prefers carriers by destination country.
- **Carrier priority rules**: Admin-defined priority (e.g. prefer DHL for certain zones).

All of these would be new implementations of `CarrierSelectionStrategyInterface`, registered in the container instead of `CheapestCarrierSelectionStrategy`.

## API Response Shape

The product import preview response now includes:

- `product` – existing extracted product data (unchanged).
- `shipping_quote` – existing shipping quote (unchanged).
- `final_pricing` – new object, or `null` if no shipping quote or if final pricing calculation fails.

**final_pricing** (when present):

| Field | Type | Description |
|-------|------|-------------|
| `product_price` | float | Unit price from product |
| `product_currency` | string | Product currency |
| `shipping_amount` | float | From shipping quote |
| `shipping_currency` | string | From shipping quote |
| `service_fee` | float | From config |
| `markup_amount` | float | Calculated from config percent |
| `subtotal` | float | Product line + shipping |
| `final_total` | float | Subtotal + fees + markup |
| `carrier` | string \| null | Selected carrier |
| `pricing_mode` | string | e.g. carrier / default |
| `estimated` | bool | true if quote used fallback data |
| `notes` | string[] | Human-readable notes |

## Error Handling

- If **shipping quote** is `null` (insufficient data or quote failure), `final_pricing` is `null`.
- If **final pricing calculation** throws (e.g. unexpected data), the exception is caught, logged, and `final_pricing` is set to `null`. The product preview and `shipping_quote` are still returned; the request does not fail.

## Unit Normalization (Weight)

Weight units are normalized internally to **kg** or **lb** only. If input contains `lbs`, it is normalized to `lb`; no other aliases are stored. This is done in `PackageNormalizer::normalizeWeightUnit()` and used by `ProductToShippingInputMapper` and config when reading default weight unit.

---

## Next Step: Cart + Order Creation Flow

The final pricing layer produces a **confirmable structure** that the client can display and then use to:

1. **Confirm product screen**: Show `final_pricing` breakdown (product, shipping, fees, total) and “Add to cart” or “Confirm”.
2. **Cart insertion**: Send the same product + shipping + final_pricing snapshot (or references) so the cart can store line item, shipping, and total without recalculating.
3. **Order creation**: When the user checks out, the backend can use the stored pricing snapshot (or recompute with the same config) to create order lines, shipping line, and total.

Recommended next backend work:

- **Cart API**: Endpoints to add an item (from import preview + `final_pricing`), update quantity, remove item, and get cart totals.
- **Order API**: Create order from cart, persisting product, shipping, and final total; optionally re-validating pricing at order creation time.
- **Idempotency**: Ensure adding the same imported product (e.g. same URL + options) is idempotent or merged by quantity.

The existing `FinalProductPricingService` can be reused when recalculating at order time (e.g. if shipping quote or config changed) by passing the same normalized product, current shipping quote, and quantity.
