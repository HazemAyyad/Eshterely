# API Reconciliation Report – Backend (Eshterely / Zayer)

**Date:** March 2025  
**Scope:** Laravel backend only. No Flutter code modified.  
**Goal:** Align backend API contracts with app expectations; fix real mismatches; produce a launch-focused reconciliation report.

---

## 1. Flutter expectations reviewed

- **File checked:** `docs/API_CONTRACT_AUDIT_FLUTTER.md` – **not found** in this repository.
- **Source of truth used:** Backend documentation and existing API contracts:
  - `docs/RELEASE_READINESS_REPORT.md`
  - `docs/ORDER_PAYMENT_INTEGRATION.md`
  - `docs/CHECKOUT_AND_ORDER_FINALIZATION.md`
  - `docs/IMPORTED_PRODUCT_CONFIRM_AND_CART.md`
  - `docs/FINAL_PRICING_LAYER.md`
  - `docs/FCM_NOTIFICATIONS.md`
  - `docs/DRAFT_ORDERS.md`
  - `docs/SHIPPING_ENGINE_FOUNDATION.md`

Expectations inferred from these docs:

- **Orders:** `payment_status`, `payment_reference`, frontend-friendly `status_key`, numeric `total`, `currency`, `estimated`, `needs_review`.
- **Payment launch:** `payment_id`, `reference`, `provider`, `checkout_url`, `status`, `order_id` (same shape for both start-payment and pay endpoints).
- **Cart:** Consistent CartItemResource shape on list, add, and update (e.g. `id`, `url`, `name`, `price`, `quantity`, `source`, `imported_product_id`, `pricing_snapshot`, `shipping_snapshot`, `needs_review`, `estimated`).
- **Imported product:** `id` (string), `shipping_quote`, `final_pricing`, `estimated`, `status`, `created_at` / `updated_at`.
- **Draft orders:** `id`, `status`, `currency`, `subtotal`, `shipping_total`, `service_fee_total`, `final_total`, `estimated`, `needs_review`, `review_state`, `items`, `created_at`, `updated_at`.
- **Notifications (FCM):** `type`, `reference_id`, `target_type`, `target_id`, `route_key`, `payload` for deep linking.
- **Errors:** Structured where applicable (e.g. `message`, `error_key` for payment eligibility).

---

## 2. Mismatches found

| Area | Mismatch | Risk |
|------|----------|------|
| **Order list/detail** | `GET /api/orders` and `GET /api/orders/{id}` used a custom `formatOrder()` that did not include `status_key`, numeric `total`, `currency`, `estimated`, or `needs_review`. Response shape differed from `OrderResource` returned by `POST /api/draft-orders/{id}/checkout`. | **High** – App could rely on `status_key` or numeric `total`; list/detail would be inconsistent with post-checkout order. |
| **Cart update** | `PATCH /api/cart/items/{id}` returned raw model attributes (e.g. `unit_price`, `product_url`) instead of the same CartItemResource shape as list/store. | **Medium** – App could assume a single cart item contract. |
| **Payment launch (pay)** | `POST /api/orders/{order}/pay` returned `CheckoutSessionResource` with only `payment_id`, `reference`, `checkout_url`, `status`. Docs and `POST /api/orders/{order}/start-payment` use `PaymentLaunchResource` with `provider` and `order_id`. | **Medium** – Two payment-start flows; response shape should match for consistent app handling. |
| **Imported product** | `ImportedProductResource` exposed `id` as integer; other resources (Order, CartItem, etc.) use string IDs. | **Low** – Type consistency for IDs across resources. |
| **Legacy checkout** | `POST /api/checkout/confirm` still creates orders with `status = in_transit` and no payment flow (documented in RELEASE_READINESS_REPORT). | **Known** – Not changed here; requires Flutter coordination to deprecate or reroute. |

No changes were made to notification payload structure (FCM data already provides `type`, `reference_id`, `target_type`, `target_id`, `route_key`, `payload`). No changes to error response structure beyond existing `message` / `error_key` usage.

---

## 3. Fixes applied

### 3.1 Order list and detail (`OrderController`)

- **File:** `app/Http/Controllers/Api/OrderController.php`
- **Change:** Extended `formatOrder()` so list and detail responses align with `OrderResource` where the app is likely to depend on it:
  - Added **`status_key`** (same mapping as `OrderResource`: `pending_review`, `pending_payment`, `paid`, `processing`, `shipped`, `delivered`, `cancelled`, or raw status).
  - Added **`total`** (float) and **`currency`** (string).
  - Added **`estimated`** and **`needs_review`** (boolean).
- **Result:** Same fields available on `GET /api/orders` and `GET /api/orders/{id}` as in the order returned after draft checkout, without removing existing display fields (`total_amount` as formatted string, `placed_date`, `shipments`, etc.).

### 3.2 Cart item update (`CartController`)

- **File:** `app/Http/Controllers/Api/CartController.php`
- **Change:** `PATCH /api/cart/items/{id}` now returns the same shape as list/store: `CartItemResource::toArray()` instead of the raw model.
- **Result:** Consistent cart item contract for list, add, and update.

### 3.3 Payment launch shape for `POST /api/orders/{order}/pay`

- **Files:** `app/Http/Resources/CheckoutSessionResource.php`, `app/Http/Controllers/Api/PaymentCheckoutController.php`
- **Change:**
  - `CheckoutSessionResource` now includes **`provider`** (default `square`) and **`order_id`** (string).
  - `PaymentCheckoutController` passes `provider` and `order_id` when building the resource.
- **Result:** Both `POST /api/orders/{order}/start-payment` and `POST /api/orders/{order}/pay` expose the same logical contract (`payment_id`, `reference`, `provider`, `checkout_url`, `status`, `order_id`) for app handling.

### 3.4 Imported product ID type

- **File:** `app/Http/Resources/ImportedProductResource.php`
- **Change:** `id` is now cast to `(string)` for consistency with other API resources.
- **Result:** Uniform ID type for imported product responses.

---

## 4. Remaining minor inconsistencies

- **Order response duality:** List/detail still use `formatOrder()` (with the new fields) while draft checkout returns `OrderResource`. Both now share `status_key`, `total`, `currency`, `estimated`, `needs_review`, `payment_status`, `payment_reference`. Remaining differences are intentional (e.g. formatted `total_amount`, `placed_date`, detailed `shipments` only on show). No further unification was done to avoid unnecessary churn.
- **Legacy `POST /api/checkout/confirm`:** Left as-is. Still creates orders with `in_transit` and no payment lifecycle. Deprecation or reroute must be done in coordination with the Flutter app (see RELEASE_READINESS_REPORT and Part C1).
- **Payment status for non‑pending orders:** For orders not in `pending_payment` or `paid`, `payment_status` can still return the raw order status (e.g. `in_transit`). Documented behavior; no change.
- **Validation errors:** Laravel’s default validation JSON (`message`, `errors`) is unchanged. Payment eligibility continues to use `message` + `error_key` for 422 responses.
- **Shipment status values:** Still free-form in admin (see RELEASE_READINESS_REPORT C2). Out of scope for this API reconciliation.

---

## 5. Verification summary

| Endpoint / area | Method | Contract alignment |
|-----------------|--------|--------------------|
| `config/bootstrap` | GET | Unchanged. |
| `countries`, `cities` | GET | Unchanged. |
| `auth/*` | POST | Unchanged. |
| `me/*` (profile, addresses, settings, fcm-token, etc.) | GET/PATCH/POST | Unchanged. |
| `cart`, `cart/items` | GET/POST/PATCH/DELETE | Cart update now returns CartItemResource. |
| `cart/create-draft-order` | POST | Unchanged; returns DraftOrderResource. |
| `draft-orders`, `draft-orders/{id}`, `draft-orders/{id}/checkout` | GET/POST | Unchanged; 422 readiness payload and 201 OrderResource. |
| `orders`, `orders/{id}` | GET | **Updated:** response includes status_key, total, currency, estimated, needs_review. |
| `orders/{order}/payments` | GET | Unchanged; PaymentResource collection. |
| `orders/{order}/start-payment` | POST | Unchanged; PaymentLaunchResource. |
| `orders/{order}/pay` | POST | **Updated:** CheckoutSessionResource now includes provider, order_id. |
| `payments/{payment}` | GET | Unchanged; PaymentResource. |
| `checkout/review`, `checkout/confirm` | GET/POST | Unchanged (confirm left as legacy path). |
| `wallet/*` | GET/POST | Unchanged. |
| `favorites` | GET/POST/DELETE | Unchanged. |
| `support/*` | GET/POST | Unchanged. |
| `notifications`, `notifications/{id}/read` | GET/PATCH | Unchanged. |
| `products/import-from-url` | POST | Unchanged; product + shipping_quote + final_pricing. |
| `imported-products/confirm`, `imported-products/{id}`, `imported-products/{id}/add-to-cart` | POST/GET | **Updated:** Imported product `id` is string. |
| `shipping/quote-preview` | POST | Unchanged; success + quote. |

**Notification payload (FCM):** Already includes `type`, `reference_id`, `target_type`, `target_id`, `route_key`, `payload`. No code changes.

---

## 6. Final assessment

- **Launch blocker:** **No.**  
- All identified contract mismatches that could break app/backend integration have been addressed with small, safe changes:
  - Order list/detail now expose `status_key`, numeric `total`, `currency`, `estimated`, and `needs_review`.
  - Cart item update returns the same resource shape as list/store.
  - Payment launch response for `POST /api/orders/{order}/pay` now matches the documented shape (including `provider` and `order_id`).
  - Imported product `id` is consistently a string.

The only remaining cross-repo risk is the **legacy `POST /api/checkout/confirm`** path. That should be resolved with the Flutter team (deprecate or route through draft order + payment flow); it was explicitly left unchanged in this pass to avoid breaking the app without coordination.

**Recommendation:** Proceed with launch from a backend API contract perspective. Coordinate with Flutter to confirm deprecation or replacement of `/checkout/confirm` and to validate the updated order and payment launch responses in the app.
