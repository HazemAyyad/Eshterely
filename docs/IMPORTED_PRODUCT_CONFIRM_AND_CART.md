# Confirm Product → Add to Cart (Imported Products)

This document describes the backend flow for confirming an imported product and adding it to the cart, including snapshot behavior and what the next task should implement.

## Overview

After the user imports a product from URL (`POST /api/products/import-from-url`), they see a preview with:

- Normalized product data (title, image, price, currency, store, etc.)
- Shipping quote (carrier, amount, estimated flag, missing_fields)
- Final pricing breakdown (product + shipping + fees + markup → final total)

The **confirm** step persists a **stable snapshot** of that preview. The **add-to-cart** step creates a cart item from the snapshot without re-importing or recalculating.

## How Confirm Product Works

**Endpoint:** `POST /api/imported-products/confirm` (auth required)

**Request body:** The client sends the same structure it received from `import-from-url` (or an equivalent preview), including:

- `source_url` (required) – product page URL
- `name` or `title` (required) – product title
- `price` (required) – product unit price
- `currency`, `image_url`, `store_key`, `store_name`, `country`, `quantity`
- Optional package fields: `weight`, `weight_unit`, `length`, `width`, `height`, `dimension_unit`
- `shipping_quote` (required) – full quote object (amount, carrier, pricing_mode, estimated, missing_fields, notes, etc.)
- `final_pricing` (required) – full pricing object (product_price, shipping_amount, service_fee, markup_amount, subtotal, final_total, estimated, notes)
- `extraction_source` (optional) – e.g. `json_ld`, `dom`

**Behavior:**

1. Request is validated (URL, name, price, shipping_quote, final_pricing, and nested rules).
2. A new `ImportedProduct` record is created with status `draft`.
3. All snapshot data is stored as provided; **nothing is recalculated** on the server.

**Response:** `201` with the saved imported product (API resource: id, title, image_url, source_url, product_price, shipping_quote, final_pricing, estimated, status, created_at, etc.).

## What Snapshot Data Is Stored

The `imported_products` table stores:

| Field | Purpose |
|-------|--------|
| `user_id` | Owner |
| `source_url`, `store_key`, `store_name`, `country` | Source and store |
| `title`, `image_url`, `product_price`, `product_currency` | Product display and price |
| `package_info` (JSON) | quantity, weight, dimensions, units |
| `shipping_quote_snapshot` (JSON) | Full shipping quote at confirm time |
| `final_pricing_snapshot` (JSON) | Full final pricing at confirm time |
| `carrier`, `pricing_mode` | From shipping quote |
| `estimated` | Whether pricing used fallbacks / is estimated |
| `missing_fields` (JSON) | e.g. `["weight", "length"]` when fallbacks were used |
| `import_metadata` (JSON) | e.g. extraction_source |
| `status` | `draft` → `added_to_cart` → `ordered` / `archived` |

Pricing and shipping are **immutable** at confirm: the user sees and confirms a specific breakdown, and that is what we store and use for cart and (later) order.

## How Add-to-Cart Works for Imported Products

**Endpoint:** `POST /api/imported-products/{imported_product}/add-to-cart` (auth required)

**Behavior:**

1. **Ownership:** The `imported_product` must belong to the authenticated user; otherwise `403`.
2. **Status:** The imported product must be in status `draft`; if already `added_to_cart` (or otherwise not draft), `422`.
3. A **cart item** is created from the snapshot:
   - Core fields (name, unit_price, quantity, currency, image_url, store_key, store_name, country, shipping_cost, weight/dimensions) are filled from the snapshot.
   - `source` is set to `imported`.
   - `imported_product_id` links to the snapshot.
   - `pricing_snapshot` and `shipping_snapshot` are copied from the imported product (so cart and checkout can show the same breakdown without recalculation).
4. The imported product’s status is updated to `added_to_cart`.

**Response:** `201` with `message`, `imported_product` (resource), and `cart_item` (id, name, price, quantity, source, pricing_snapshot, shipping_snapshot, etc.).

Cart list (`GET /api/cart` or `GET /api/cart/items`) includes `imported_product_id`, `pricing_snapshot`, and `shipping_snapshot` for each item so the app can distinguish imported items and show their snapshots.

## Why Pricing Is Stored as a Snapshot

- **User expectation:** The user confirmed a specific total and breakdown; that is what they must see in cart and at checkout.
- **Stability:** We do not depend on re-fetching or re-extracting the product page; the snapshot is the source of truth for that confirmed item.
- **Admin/config:** Service fee, markup, and shipping logic are applied at import/preview time; the snapshot stores the **result** of that configuration for this item.

Recalculation (e.g. “refresh quote”) can be added later as an explicit action (new endpoint or re-import flow), not as a silent background change.

## Estimated Pricing and Missing Fields

If the product lacked weight or dimensions, the shipping engine uses fallback values and sets `estimated: true` and `missing_fields` (e.g. `["weight", "length"]`). These are:

- Stored in the imported product snapshot and in the cart item’s `shipping_snapshot`.
- Exposed in the API (`estimated`, `missing_fields`) for cart review and admin review.

The frontend can show a notice that the quote is estimated and may change after review.

## Draft Order Foundation

`App\Services\DraftOrderService::createFromImportedProduct(ImportedProduct $imported)` is a placeholder for creating a **draft order** from an imported product snapshot. It currently returns `null`. The next task should implement:

- Creating an `Order` with a draft status (e.g. `draft` or `pending_placement`).
- Creating `OrderShipment` and `OrderLineItem` from the snapshot (and from cart items when the flow is cart-based).
- Wiring this into cart review and payment-safe finalization.

## Next Task Preview

**Cart review + order creation + payment-safe finalization**

- Use cart items (including imported ones with `pricing_snapshot` / `shipping_snapshot`) for checkout review.
- Create orders from the cart using snapshot data (no silent recalculation).
- Implement draft → placed flow and payment intent/finalization so that the amount charged matches the confirmed snapshot.

## API Summary

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/imported-products/confirm` | Yes | Confirm preview and create imported product snapshot (draft) |
| GET | `/api/imported-products/{id}` | Yes | Get own imported product (ownership enforced) |
| POST | `/api/imported-products/{id}/add-to-cart` | Yes | Add draft imported product to cart; status → added_to_cart |

Existing cart endpoints (`GET/POST /api/cart/items`, etc.) are unchanged; imported items are cart items with `source=imported` and non-null `imported_product_id`, `pricing_snapshot`, and `shipping_snapshot`.
