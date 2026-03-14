# Payment Domain Foundation — Summary

## What Was Added

### 1. Database
- **`payments`** — Core payment record: `user_id`, `order_id`, `provider` (default `square`), `currency`, `amount`, `status`, `provider_payment_id`, `provider_order_id`, `idempotency_key`, `reference` (unique), `failure_code`, `failure_message`, `paid_at`, `metadata` (JSON), timestamps. Indexes on `idempotency_key`, `order_id`+`status`, `status`.
- **`payment_attempts`** — Per-attempt log: `payment_id`, `provider`, `attempt_no`, `request_payload`, `response_payload`, `status`, `error_message`. Index on `payment_id`+`attempt_no`.
- **`payment_events`** — Audit trail: `payment_id`, `source` (system/webhook/admin), `event_type`, `payload`, `notes`. Index on `payment_id`+`created_at`.

### 2. Enums / Constants
- **`App\Enums\Payment\PaymentStatus`** — `pending`, `requires_action`, `processing`, `paid`, `failed`, `cancelled`, `refunded`; `label()`, `isTerminal()`.
- **`App\Enums\Payment\PaymentEventSource`** — `system`, `webhook`, `admin`.
- **`App\Enums\Payment\PaymentEventType`** — Constants for event types (e.g. `payment.created`, `payment.paid`, `payment.failed`, `payment.attempt.created`).

### 3. Eloquent Models
- **`Payment`** — BelongsTo User, Order; HasMany PaymentAttempt, PaymentEvent. Casts: `status` (enum), `paid_at` (datetime), `metadata` (array). `isPaid()`, `isTerminal()`.
- **`PaymentAttempt`** — BelongsTo Payment; JSON casts for request/response payloads.
- **`PaymentEvent`** — BelongsTo Payment; `source` (enum), `payload` (array).
- **`Order`** — HasMany Payment; admin order show loads `payments`.

### 4. Service Layer
- **`App\Services\Payments\PaymentReferenceGenerator`** — Generates unique reference: `PAY-YYYYMMDD-XXXXXX`.
- **`App\Services\Payments\PaymentService`** — Methods: `createPendingPaymentForOrder()`, `createAttempt()`, `markProcessing()`, `markPaid()`, `markFailed()`, `markCancelled()`, `addEvent()`. Safe transitions (no duplicate paid), `paid_at` set only when becoming paid, events recorded on status change.

### 5. API (Read-only)
- **`GET /api/payments/{payment}`** — Single payment; authorized via `PaymentPolicy` (user must own payment via `user_id` or order’s user).
- **`GET /api/orders/{order}/payments`** — List payments for order; user must own the order.
- **`PaymentResource`** — Response fields: `id`, `reference`, `provider`, `amount`, `currency`, `status`, `failure_message`, `paid_at`, `created_at`, `updated_at`.

### 6. Admin
- Admin order show loads `payments` so the view can display them later without further backend changes.

### 7. Factories & Tests
- **`OrderFactory`**, **`PaymentFactory`** (states: `forOrder`, `pending`, `paid`, `failed`).
- **Feature tests:** create pending payment, mark paid (and idempotent), mark failed, user can retrieve own payment, user cannot retrieve another’s payment, order payments list for owner, order payments blocked for another user’s order.

---

## Why It Was Needed

- Checkout today creates orders and optionally deducts wallet balance; there is no structured payment lifecycle or provider-agnostic model.
- Square (and future providers) need: a clear payment record per order, attempts, idempotency, status flow, and an event log for webhooks and support.
- This foundation keeps the current checkout flow unchanged while adding a proper payment domain for Square checkout/sessions and webhooks.

---

## Next Task: Square Checkout Integration

1. **Square SDK & config** — Add Square PHP SDK, store `application_id`, `location_id`, and `access_token` (or equivalent) in config/env; keep secrets out of code.
2. **Checkout session creation** — When the app is ready to pay with Square (e.g. after order creation or from a “Pay with Square” step), call `PaymentService::createPendingPaymentForOrder()`, then use Square API to create a checkout session (or payment link) and store `idempotency_key` / first attempt via `createAttempt()`; optionally set `requires_action` or redirect URL in metadata.
3. **Frontend** — Expose a “payment URL” or “checkout session id” from the API so the Flutter app can open Square Checkout or embed it; do not change existing checkout UX until this is explicitly required.
4. **Webhooks** — Add a dedicated webhook route (e.g. `POST /webhooks/square`), verify signature, map Square events to `PaymentEventType` and `PaymentStatus`, then call `PaymentService` to update status (`markPaid`, `markFailed`, etc.) and `addEvent(..., PaymentEventSource::Webhook, ...)`.
5. **Idempotency** — Use `payments.idempotency_key` (and Square’s idempotency) when creating payments/checkout sessions to avoid duplicate charges.
6. **Optional** — After successful payment, set `order.transaction_id` or similar from `payment.provider_payment_id` if the current order flow expects it; keep changes minimal and non-breaking.

---

*Payment foundation only; no Square API calls or UI changes in this deliverable.*
