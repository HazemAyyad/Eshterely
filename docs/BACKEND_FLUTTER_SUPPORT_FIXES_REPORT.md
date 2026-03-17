## Backend Flutter Support Fixes Report

Repository: Laravel backend for **Eshterely**

Date: 2026-03-17

### 1) Issues found affecting Flutter

- **Checkout totals were not backend-truthful**
  - `/api/checkout/review` injected fake values (default shipping `12`, insurance `5`, consolidation `45`) and returned money as formatted strings only.
  - Result: inconsistent totals, $0.00 scenarios, and unclear estimated vs final state.

- **Square payment launch could fail due to invalid order status**
  - `/api/checkout/confirm` created orders with status `in_transit`, but `/api/orders/{order}/start-payment` only allows `pending_payment`.
  - Result: “payment launch not opening / failing” for real orders created from checkout.

- **Wallet top-up backend was faking success**
  - `/api/wallet/top-up` immediately credited the wallet balance without Square or webhook confirmation.
  - Result: top-up UI existed but was not a real end-to-end flow.

- **Order detail + tracking payloads were missing important detail**
  - Order line items had snapshot fields in DB (product/pricing snapshot) but API resources didn’t expose them.
  - Shipment tracking showed “in transit” without any event timeline because only `order_tracking_events` were returned (often empty), while real timeline data can exist in `order_shipment_events`.

- **Address API type contract mismatch**
  - `/api/me/addresses` returned `id` as string, but `POST/PATCH` returned raw Eloquent models with numeric ids.
  - `country_id` / `city_id` sometimes returned code strings and sometimes numeric ids.
  - Result: Flutter parsing crash from int/string mismatch.

### 2) Fixes implemented (incremental, backend-only)

#### A) Confirm Product / Import pricing truth (Parts 1–2)

- **Added a single explicit `pricing` object** to product import response and imported product resource:
  - Includes: `unit_price`, `quantity`, `subtotal`, `shipping_amount`, `shipping_estimated`, `needs_review`, `total`, `currency`, and a `breakdown[]`.
  - **No fake duties/tax fields were added.**

#### B) Checkout + wallet payable breakdown (Parts 3–4)

- `/api/checkout/review` now:
  - **Stops inventing** shipping/insurance/consolidation values.
  - Adds `pricing` numeric breakdown:
    - `subtotal`, `shipping`, `wallet_balance`, `wallet_applied_amount`, `amount_due_now`, `total`
    - `estimated`, `needs_review`, `shipping_estimated`
  - Keeps legacy formatted string keys for compatibility.

- `/api/checkout/confirm` now:
  - Creates orders in `pending_payment` (or `under_review` when estimated/needs_review).
  - Returns `pricing` with `amount_due_now` and `payment_required`.
  - Wallet is only **immediately deducted** when it fully covers the order (and order is not `under_review`), to avoid external-payment failure scenarios.

#### C) Wallet top-up end-to-end support (Part 5)

- Implemented a minimal real wallet top-up flow using Square payment links:
  - New table: `wallet_top_up_payments`
  - `/api/wallet/top-up` now creates a pending top-up payment and returns `checkout_url`.
  - Wallet balance is updated **only after Square webhook** confirms payment as `COMPLETED`.
  - Webhook credits wallet **idempotently** (won’t double-credit on duplicate webhook).

#### D) Order snapshot completeness (Part 6)

- `OrderItemResource` now exposes:
  - `product_snapshot`, `pricing_snapshot`, `missing_fields`, and an image fallback from snapshot.
  - Adds `unit_price` explicitly.

#### E) Shipment tracking payload improvements (Part 7)

- Order detail payload now includes shipment timeline:
  - Adds `events[]` from `order_shipment_events` with `type`, `label`, `time`, `location`, etc.
  - Adds `has_events` and `latest_update`.
  - Adds key logistics fields where available: `carrier`, `tracking_number`, `shipment_status`, `gross_weight_kg`, `dimensions`.

#### F) Address contract normalization (Part 8)

- Added `AddressResource` used consistently for:
  - `GET /api/me/addresses`
  - `POST /api/me/addresses`
  - `PATCH /api/me/addresses/{id}`
- Stabilized types:
  - `id` is always a string
  - `country_id` / `city_id` are consistently string codes (backward compatible with the existing list response)
  - numeric db ids are exposed as `country_db_id` / `city_db_id`
  - `phone` is always a string or null

### 3) Intentionally deferred / not changed

- No architecture rewrite of shipping engine, admin configuration, Square order payment core, or order/shipment foundations.
- No duties/tax system was introduced; payloads do not fabricate duties/taxes.

### 4) Readiness assessment for affected Flutter screens

- **Confirm Product / Import**: backend now exposes a consistent `pricing` structure suitable as source-of-truth.
- **Cart / Checkout Review & Pay**: backend now returns explicit numeric breakdown including wallet application and `amount_due_now`, with flags for estimated/review-required states.
- **Square Launch**: checkout now creates payment-eligible orders (`pending_payment`) so `/start-payment` can succeed when payment is required.
- **Wallet Top-up**: now supported end-to-end with Square + webhook (no fake funding).
- **Order Detail**: resources now preserve snapshots and image URLs better.
- **Tracking**: shipment event timeline is included when available, and empty event sets are explicit.
- **Addresses**: response types normalized to prevent Flutter parsing crashes.

