# Imported Cart Review

Technical summary of the cart review layer for imported products in the backend.

---

## How imported cart review works

- After **confirming** an imported product (`POST /api/imported-products/confirm`), an `ImportedProduct` record is created with status `draft`.
- When **adding to cart** (`POST /api/imported-products/{id}/add-to-cart`), a cart item is created from the snapshot only (no re-import or recalculation).
- The cart item stores:
  - **Review metadata:** `review_status`, `needs_review`, `estimated`, `missing_fields`, `carrier`, `pricing_mode`, `source_type = imported`
  - **Snapshots:** `pricing_snapshot`, `shipping_snapshot`
  - **Source:** `imported_product_id`, `source_url` (product_url), `source_store` (store_name/store_key)
- **CartItemReviewService** determines `needs_review` using rule-based logic (estimated, missing fields, fallback used, auto carrier, incomplete pricing snapshot).
- The **cart** API (`GET /api/cart`) returns these fields for each item; the frontend can identify imported items and show review state.

---

## Why imported products may need review

- **Estimated shipping or pricing:** The product lacked weight/dimensions, so the system used default values and set `estimated: true`.
- **Missing fields:** `missing_fields` (e.g. `["weight", "length"]`) when fallbacks were used.
- **Fallback defaults used:** Notes contain phrases like fallback/default/estimated for shipping or pricing.
- **Carrier auto-selection:** `carrier = auto` or `auto_selected`.
- **Incomplete pricing snapshot:** Missing `final_total` in `final_pricing_snapshot`.

In these cases `needs_review = true` is set so the user or admin knows the item may need review before order placement.

---

## Why pricing is snapshot-based

- **User agreement:** The user confirmed a specific total and breakdown; that is what is shown in cart and at payment.
- **Data stability:** We do not rely on re-fetching the page or re-extraction; the snapshot is the source of truth for that item.
- **Shipping/fee config:** Applied at import/preview time; the result is stored in the snapshot.

Recalculation (e.g. “refresh shipping”) can be added later as an explicit action (endpoint or re-import), not as a silent change when reading the cart.

---

## How the confirm trust model will evolve

- **Current behaviour:** Confirm accepts product + shipping + final pricing from the client and stores it as-is (no recalculation).
- **Current preparation:** Optional support for `preview_token` and `preview_id` in the request, stored in `import_metadata`; **PreviewVerificationService** exists as a placeholder for future verification.
- **Planned evolution:**
  - **preview_token:** Reference to a server-issued token from the import/preview flow.
  - **Server-side preview cache:** Store preview payload keyed by token/id for verification at confirm.
  - **Signed preview identifiers:** Sign `preview_id` so confirm can verify the payload was not tampered with.

Confirm will remain backward compatible with current requests; when verification is enabled, `preview_token`/`preview_id` can be validated against cache or signature before persisting the snapshot.

---

## API summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/cart` or `/api/cart/items` | List cart items (with review fields for imported items) |
| POST | `/api/imported-products/confirm` | Confirm imported preview and create snapshot |
| POST | `/api/imported-products/{id}/add-to-cart` | Add confirmed product to cart (sets needs_review) |

Additional response fields for imported cart items: `source_type`, `imported_product_id`, `review_status`, `needs_review`, `estimated`, `missing_fields`, `pricing_snapshot`, `shipping_snapshot`, `carrier`, `pricing_mode`, `source_url`, `source_store`.
