# Order-Centric Payment Integration

This document describes how finalized orders connect to Square for payment, how payment records are linked to orders, how webhooks update order status, how snapshot totals are protected, and how draft orders behave after conversion.

---

## Order-centric payment start

Payment is started from a **real order**, not from draft or cart.

**Endpoint:** `POST /api/orders/{order}/start-payment` (authenticated)

**Flow:**

1. **Validate ownership** — The authenticated user must own the order (404 if not).
2. **Check eligibility** — Order must be eligible for payment (see Payment eligibility rules). If not, returns `422` with structured error.
3. **Create payment record** — A `Payment` is created and linked to the order (`order_id`, `user_id`). Amount and currency come from the order; when present, **order_total_snapshot** is used (payment-safe).
4. **Create Square checkout session** — Square Payment Link is created using the **same snapshot amount** (no recalculation). `provider_order_id` is stored when Square returns it.
5. **Return payment launch data** — Response includes: `payment_id`, `reference`, `provider`, `checkout_url`, `status`, `order_id`.

The client uses `checkout_url` to redirect the user to Square Checkout. After the user pays (or cancels), Square sends webhooks; the backend updates the payment and order accordingly.

---

## Payment records and link to orders

Each payment record stores:

- **order_id** — Links the payment to the order.
- **user_id** — Order owner (set from order when creating payment).
- **provider** — e.g. `square`.
- **amount** — From order snapshot when available (`order_total_snapshot ?? total_amount`).
- **currency**, **status**, **idempotency_key**, **reference**
- **provider_payment_id**, **provider_order_id** — Filled when Square returns them (e.g. after checkout or via webhook).
- **failure_code**, **failure_message** — Set when payment fails.
- **paid_at** — Set when payment becomes paid.
- **metadata** — Optional context.

**payment_attempts** and **payment_events** are used consistently: each start-payment creates an attempt; webhook and status changes add events. This supports auditing and support.

---

## Payment eligibility rules

An order can start payment only if:

- **Order status** is `pending_payment`. Any other status (e.g. `paid`, `cancelled`, `in_transit`) returns `invalid_order_status`.
- **No successful payment** already exists for the order. If a payment with status `paid` exists, returns `already_paid`.
- **Order is not cancelled** (`status !== cancelled`). Returns `order_cancelled` if cancelled.
- **No unresolved blocking issue** — The service is structured so future flags (e.g. `needs_admin_review`, `needs_reprice`, `needs_shipping_completion`) can be added without rewriting the flow; currently no additional block is applied.

Structured API errors return `error_key` and `message`, e.g.:

- `already_paid` — "Order is already paid."
- `invalid_order_status` — "Order status does not allow payment. Only orders with status pending_payment can start payment."
- `not_eligible_for_payment` — "Order has unresolved issues and is not eligible for payment."

---

## Square order payment launch (snapshot-only)

When creating the Square checkout/payment link:

- **Amount** is taken from the order: `order_total_snapshot ?? total_amount`. No recalculation of shipping, pricing, or product price.
- **No draft or cart data** is used; only the stored order and its snapshots.
- Payment amount is therefore always consistent with what was finalized at checkout.

If repricing is needed later, it must be an **explicit** workflow (e.g. admin reprice or “refresh quote”), not a hidden recalculation at payment start.

---

## Webhook → order synchronization

When Square sends a payment webhook (e.g. `payment.updated` with status `COMPLETED`):

1. **Payment** — The existing webhook logic marks the payment as **paid**, sets `paid_at`, and stores `provider_payment_id` / `provider_order_id` when present. This is **idempotent**: duplicate webhooks do not change an already-paid payment.
2. **Order** — In the same flow, when the payment is marked paid, the backend runs **order sync**: if the order’s status is still `pending_payment`, it is updated to **paid** and `placed_at` is set. If the order is already paid or in another state, no change is made (idempotent).

If the webhook indicates **failed** or **cancelled**:

- The **payment** is updated to failed/cancelled and failure details are stored when provided.
- The **order** is **not** updated to paid; it remains in a safe unpaid state (e.g. `pending_payment`). Duplicate webhook delivery does not cause duplicate transitions.

---

## Snapshot totals and payment safety

Throughout the payment lifecycle:

- **No silent recalculation** of shipping, final pricing, or product price.
- **No silent modification** of order totals.
- Payment creation and Square checkout use **stored order snapshots** (`order_total_snapshot`, and the same amount is stored on the payment record).

Any future repricing or shipping refresh must be an explicit step (e.g. dedicated endpoint or admin action), not something that happens automatically during payment start or webhook handling.

---

## Draft order post-conversion state

When a draft order is converted into a real order (via `POST /api/draft-orders/{id}/checkout`):

1. The **order** is created with `status = pending_payment` and linked to the draft via `draft_order_id`.
2. The **draft order** is updated so it is no longer an active draft:
   - **status** is set to **converted** (`DraftOrder::STATUS_CONVERTED`).
   - **converted_order_id** is set to the new order’s id.
   - **converted_at** is set to the current timestamp.

Converted draft orders are therefore clearly distinguishable from active drafts and are linked to the resulting order for auditing and support.

---

## API summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/orders/{order}/start-payment` | Start payment for an order (ownership + eligibility checked; returns payment launch data including `checkout_url`). |
| GET | `/api/orders/{order}/payments` | List payments for an order. |
| GET | `/api/payments/{payment}` | Get a single payment (policy: own payment only). |

**Order responses** (e.g. OrderResource, order list/detail) expose **payment_status** (e.g. `pending_payment`, `paid`) and **payment_reference** (reference of a paid payment when applicable).

**Payment launch response:** `payment_id`, `reference`, `provider`, `checkout_url`, `status`, `order_id`.

---

## Next steps (preview)

- Admin order review and operations (e.g. cancel, reprice, mark shipped).
- FCM (or other) notifications for order and payment events.
