# Square Webhook Implementation Summary

## Supported Webhook Events

| Event             | Handled | Description |
|------------------|--------|-------------|
| **payment.created** | Yes (mandatory) | New payment created; payment status is synced. |
| **payment.updated** | Yes (mandatory) | Payment status/fields changed; internal payment status and `paid_at` / `provider_payment_id` are updated. |
| **refund.updated**  | Yes (optional)  | Logged as a webhook event on the related payment if the payment can be resolved. |
| **order.updated**   | Yes (optional)  | Logged as a webhook event on the related payment if the payment can be resolved. |

Unsupported event types are ignored and a 200 response is still returned so Square does not retry.

---

## Internal Status Mapping

Square payment status is mapped to our `PaymentStatus` enum as follows:

| Square status   | Internal status   |
|-----------------|-------------------|
| `COMPLETED`     | `paid`            |
| `APPROVED`      | `processing`      |
| `PENDING`       | `processing`      |
| `CANCELED` / `CANCELLED` | `cancelled` |
| `FAILED`        | `failed`          |

When status becomes **paid**:

- `paid_at` is set if not already set.
- `provider_payment_id` is stored (Square payment ID).
- `provider_order_id` is kept or set from the payload if missing.
- Duplicate webhooks do not change `paid_at` or create duplicate paid transitions.

When status becomes **failed** or **cancelled**:

- Failure code and message are stored when available.
- A `PaymentEvent` is recorded with source `webhook`.

---

## Implementation Details

- **Endpoint:** `POST /webhooks/square` (registered in `routes/web.php`, no auth, CSRF exempt).
- **Verification:** Square signature is verified via `x-square-hmacsha256-signature` using `Square\Utils\WebhooksHelper` (notification URL + raw body + signature key). Invalid or missing signature returns **403**.
- **Payment resolution:** Payment is looked up by, in order: `provider_payment_id`, `provider_order_id`, `reference`, or `metadata.reference`. If no payment is found, the webhook is accepted (200) and a safe log entry is written; no exception is thrown.
- **Payment events:** Every recognized webhook adds a `PaymentEvent` with `source = webhook`, `event_type` set to the Square event type (e.g. `payment.updated`), and a sanitized payload (no secrets).
- **Order synchronization:** Only payment lifecycle is updated. No change to order status or other order flow; a payment-paid event is logged for future order handling. Order sync can be added in a later task when the order model is ready.

---

## Configuration

- `config/square.php`: `webhook_signature_key`, `webhook_notification_url`, and (testing only) `webhook_skip_verification`.
- `.env` / `.env.example`: `SQUARE_WEBHOOK_SIGNATURE_KEY`, `SQUARE_WEBHOOK_NOTIFICATION_URL`. The notification URL must match exactly the URL configured in the Square Developer portal.

---

## Tests

Feature tests in `tests/Feature/SquareWebhookTest.php` cover:

- Valid `payment.updated` (COMPLETED) marks payment as paid and sets `provider_payment_id` and `paid_at`.
- Invalid signature returns 403.
- Duplicate paid webhook does not duplicate paid transition or change `paid_at`.
- Unknown payment reference returns 200 and does not crash.
- Failed payment webhook marks payment as failed and stores failure details.
- Payment resolved by `provider_payment_id`.
- Empty body returns 400.

Verification is bypassed in tests via `square.webhook_skip_verification` when needed; the invalid-signature test runs verification and expects 403.

*Note: The project’s test suite currently uses SQLite in-memory; some migrations use MySQL-specific syntax (`MODIFY`), which can cause test failures. Once the test database setup is fixed, these webhook tests should run as above.*

---

## What the Next Task Should Be

1. **Order synchronization:** When payment becomes `paid`, update the related order (e.g. set order status to “paid” or “confirmed”, or trigger fulfillment) if the order model and business rules are ready. Use the existing `payment.paid` webhook event as the trigger.
2. **Optional:** Subscribe to `payment.created` in the Square Developer portal if you want to react to creation separately; `payment.updated` is sufficient for status sync.
3. **Production:** Set `SQUARE_WEBHOOK_SIGNATURE_KEY` and `SQUARE_WEBHOOK_NOTIFICATION_URL` in production and register the exact webhook URL (`https://your-domain.com/webhooks/square`) in the Square Developer portal. Do not set `SQUARE_WEBHOOK_SKIP_VERIFICATION` in production.
