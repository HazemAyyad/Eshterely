# Checkout and Order Finalization

This document describes how draft orders are evaluated for checkout readiness, converted into payment-safe orders, and how snapshot integrity is preserved. It also outlines the architecture for future granular review states and optional cart restoration.

---

## Checkout readiness rules

The **CheckoutReadinessService** evaluates whether a draft order is ready for checkout. The following conditions **block** checkout (order creation is not allowed):

| Condition | Description |
|-----------|-------------|
| `needs_review = true` | Draft or any item is flagged for review. |
| Any item `estimated = true` | Pricing or shipping was estimated (e.g. fallback weight/dimensions). |
| `missing_fields` not empty | One or more items have missing fields (e.g. weight, dimensions). |
| Shipping carrier unresolved | Any item has `carrier` equal to `auto` or `auto_selected`. |
| Pricing incomplete | Draft `final_total_snapshot` is missing or ≤ 0, or any item lacks a valid line total in its pricing snapshot. |
| No items | Draft has no line items. |

The service returns a structured response:

- **ready_for_checkout** (bool): `true` only when no blocking issues exist.
- **needs_review** (bool): From the draft order.
- **warnings** (array): Non-blocking notices (e.g. estimated items, review state flags).
- **blocking_issues** (array): Human-readable reasons checkout is blocked.

The checkout endpoint uses this result: if there are blocking issues, it returns `422` with this structure; if ready, it creates the order and returns `201` with the order resource.

---

## Snapshot-based payment safety

Orders created from draft orders are **payment-safe** because:

1. **No silent recalculation**  
   During checkout we do **not** recalculate shipping, pricing, or product price. All amounts come from stored snapshots.

2. **Stored snapshot totals on the order**  
   The order stores:
   - **order_total_snapshot** – total amount (from draft `final_total_snapshot`)
   - **shipping_total_snapshot** – from draft `shipping_total_snapshot`
   - **service_fee_snapshot** – from draft `service_fee_total_snapshot`

   Payment must use only these (and/or line-level snapshots). No runtime recalculation should be used for the charged amount.

3. **Line-level snapshots**  
   Each order line item stores:
   - **product_snapshot** – copied from the draft order item
   - **pricing_snapshot** – copied from the draft order item  
   So audit and dispute resolution can rely on the same data the user saw at checkout.

If recalculation is ever needed (e.g. admin reprice or shipping refresh), it must be triggered **explicitly** (e.g. dedicated endpoint or workflow), not automatically during checkout or payment.

---

## Draft-to-order transition

1. **Endpoint**: `POST /api/draft-orders/{draftOrder}/checkout`
2. **Flow**:
   - Validate ownership (user must own the draft order).
   - Run **CheckoutReadinessService::evaluate** on the draft.
   - If **blocking_issues** are present → return `422` with readiness payload.
   - If **ready_for_checkout** → **OrderFinalizationService::createOrderFromDraft** creates the order.
3. **Order creation** (no recalculation):
   - Copy draft-level snapshot totals to the order (`order_total_snapshot`, `shipping_total_snapshot`, `service_fee_snapshot`).
   - Set order **status** = `pending_payment`.
   - Group draft items by `product_snapshot.country` and create one **OrderShipment** per country; copy shipping snapshot from draft items into each shipment.
   - For each draft item, create an **OrderLineItem** with:
     - product_snapshot, pricing_snapshot, review_metadata, estimated, missing_fields
     - draft_order_item_id, source_type, cart_item_id, imported_product_id (origin tracking)
   - Preserve `estimated` and `needs_review` on the order from the draft.

Cart items linked to the draft remain linked (they are not deleted). This supports auditing and future cart restoration flows.

---

## Future review state expansion

The system is structured to support **granular review states** without changing the overall flow:

- **DraftOrder** already has:
  - `review_state` (JSON) for extensible flags
  - Constants: `REVIEW_STATE_NEEDS_ADMIN_REVIEW`, `REVIEW_STATE_NEEDS_REPRICE`, `REVIEW_STATE_NEEDS_SHIPPING_COMPLETION`

- **CheckoutReadinessService** documents how to extend: add checks for these keys in `review_state` so that when they are set, they become **blocking_issues** until resolved.

- **DraftOrderService::buildReviewState** can be extended to set these keys when the corresponding conditions are detected (e.g. after an admin or pricing workflow runs).

No full implementation of these states is required yet; the code is prepared so they can be added with minimal changes.

---

## Draft order origin tracking

For admin review, order auditing, and optional cart restoration:

- **DraftOrderItem** stores:
  - **source_type** – e.g. `imported`, `paste_link`, `webview`
  - **cart_item_id** – link back to the cart item
  - **imported_product_id** – when the item came from an imported product

- **OrderLineItem** (created from draft) stores:
  - **draft_order_item_id** – link back to the draft item
  - **source_type**, **cart_item_id**, **imported_product_id** – copied from the draft item

These fields are exposed in the API (e.g. DraftOrderItemResource, and can be added to OrderItemResource if needed for admin).

---

## Optional cart restoration (preparation)

The data structure supports later **restoring draft items to the cart**:

- **DraftOrderItem** has full snapshots (product_snapshot, shipping_snapshot, pricing_snapshot) and **cart_item_id**.
- **OrderLineItem** has **draft_order_item_id**, so we can trace order line → draft item → cart item (if still present) or recreate a cart item from the draft item’s snapshots.

A full restore flow (e.g. “Return this order’s items to cart”) is not implemented yet; the schema and relations are in place to add it when required.

---

## API summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/draft-orders` | List current user’s draft orders. |
| GET | `/api/draft-orders/{id}` | Show draft order (ownership enforced). |
| POST | `/api/draft-orders/{id}/checkout` | Evaluate readiness; if ready, create order and return Order resource (201). If not ready, return 422 with readiness payload. |

**Order resource** (after checkout): `id`, `status`, `total`, `currency`, `estimated`, `needs_review`, `items`, `created_at`, `order_number`.

**Order item resource**: `id`, `name`, `store_name`, `sku`, `price`, `quantity`, `image_url`, `estimated`, `needs_review`.

---

## Next steps (preview)

- Payment integration (charge using `order_total_snapshot` and stored snapshots).
- Order processing workflow (e.g. transition from `pending_payment` to `paid` / `in_transit`).
- Admin review panel (use `needs_review`, `review_state`, and origin fields for triage and actions).
