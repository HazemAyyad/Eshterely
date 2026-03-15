# Draft Orders from Cart

Technical summary of draft order creation from cart items (including imported-product cart items) in the Eshterely backend.

---

## Overview

Draft orders provide a **stable snapshot** of the user's cart, ready for later checkout or payment-safe finalization. Creation is **snapshot-based only**: no re-import, no recalculation of shipping or final pricing.

The system builds on:

- Cart items with `pricing_snapshot`, `shipping_snapshot`, and review metadata
- `DraftOrderService` for converting cart → draft order
- Persistence in `draft_orders` and `draft_order_items`

---

## How Draft Orders Are Created

1. **Endpoint:** `POST /api/cart/create-draft-order` (authenticated).
2. **Input:** None (uses the authenticated user's **active** cart).
3. **Process:**
   - Load all cart items for the user where `draft_order_id` is `null` (active cart).
   - If empty → `422` with message "Cart is empty."
   - Build one `DraftOrder` and one `DraftOrderItem` per cart item.
   - **Copy only** from each cart item:
     - `product_snapshot` (name, unit_price, image_url, store, etc.)
     - `shipping_snapshot`
     - `pricing_snapshot`
     - Review metadata (e.g. `review_status`, `needs_review`, `estimated`, `carrier`, `pricing_mode`, `missing_fields`).
   - Aggregate order-level totals from each item’s `pricing_snapshot` (subtotal, shipping, service_fee, final_total) **without recalculating**.
   - Set draft order `estimated` / `needs_review` from item flags (see below).
   - Set each cart item’s `draft_order_id` to the new draft (post-conversion behavior).
4. **Response:** `201` with the created draft order resource (including `items`).

No recalculation of shipping or final pricing occurs during this flow.

---

## How Snapshot Pricing Works

- **Cart items** (especially from imported products) store:
  - `pricing_snapshot`: e.g. `subtotal`, `shipping_amount`, `service_fee`, `final_total`, `estimated`, `notes`.
  - `shipping_snapshot`: e.g. `amount`, `carrier`, `currency`, `notes`.

- **Draft order creation:**
  - Reads these snapshots from each cart item.
  - Sums `subtotal`, `shipping_amount`, `service_fee`, and `final_total` (with fallbacks when keys are missing) to set:
    - `subtotal_snapshot`, `shipping_total_snapshot`, `service_fee_total_snapshot`, `final_total_snapshot` on the draft order.
  - Copies each item’s `pricing_snapshot` and `shipping_snapshot` onto the corresponding `DraftOrderItem`.

Pricing and shipping at draft order level are therefore **derived only from stored snapshots**, not from any live quote or pricing service.

---

## Review State Propagation

- **From cart items to draft order:**
  - If **any** cart item has `needs_review === true` → draft order `needs_review = true`.
  - If **any** cart item has `estimated === true` → draft order `estimated = true`.

- **Draft order** also has a `review_state` JSON field for future granular flags, e.g.:
  - `needs_admin_review`
  - `needs_reprice`
  - `needs_shipping_completion`  
  These are **not** implemented yet; the structure is in place so the system can evolve without changing the high-level flow.

- **Draft order items** store:
  - `estimated`, `missing_fields`
  - `review_metadata` (e.g. `review_status`, `needs_review`, `estimated`, `carrier`, `pricing_mode`).

---

## Cart Post-Conversion Behavior

After a draft order is created from the cart:

- Each converted cart item gets **`draft_order_id`** set to the new draft order.
- **Active cart** = items where `draft_order_id` is `null`.
  - `GET /api/cart` (and `/api/cart/items`) return only active cart items.
  - Cart update/delete and checkout use only active cart items.

So converted items are **attached to the draft** and no longer appear in the active cart; there is no duplicate “in cart” and “in draft” state for the same item.

---

## Future Verification Integration

The confirm flow currently accepts client-provided product and pricing data. The architecture is prepared for stronger verification:

- **PreviewVerificationService** exists as a placeholder (e.g. `verifyPreviewReference(preview_token, preview_id)`).
- **DraftOrderService** can receive `PreviewVerificationService` via constructor; when verification is enabled later, draft order creation can:
  - Validate snapshot or preview references before or during draft creation.
  - Use server-side preview cache or signed identifiers without changing the existing confirm or cart APIs.

No changes to the confirm or add-to-cart flow are required for the current draft order implementation; verification can be added when preview_token / server-side preview cache / signed preview identifiers are implemented.

---

## API Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/cart/create-draft-order` | Create a draft order from the user’s active cart. Returns the draft order resource (with items). |
| GET | `/api/draft-orders` | List the authenticated user’s draft orders. |
| GET | `/api/draft-orders/{id}` | Show one draft order (ownership enforced). |

**Draft order resource** (and list item) includes: `id`, `status`, `currency`, `subtotal`, `shipping_total`, `service_fee_total`, `final_total`, `estimated`, `needs_review`, `review_state`, `notes`, `warnings`, `items`, `created_at`, `updated_at`.

**Draft order item** includes: `id`, `cart_item_id`, `imported_product_id`, `product_snapshot`, `shipping_snapshot`, `pricing_snapshot`, `quantity`, `review_metadata`, `estimated`, `missing_fields`.

---

## Validation and Ownership

- **Create draft order:** Only the authenticated user’s cart is read; no way to create a draft from another user’s cart.
- **Empty cart:** Creating a draft with no active items returns `422` with message "Cart is empty."
- **List/Show draft orders:** Policy ensures users can only list and view their own draft orders.

---

## Next Steps (Preview)

- Checkout preparation using draft orders.
- Payment-safe order finalization from draft (e.g. draft → placed order with payment intent).
