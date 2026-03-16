# Admin Order Review and Operations

This document describes how admin order review and operations work, how payment state affects order operations, how review and override actions are tracked, and what the next task should implement.

---

## How admin order review works

- **Order list** (admin orders index) shows: payment status, order status, estimated flag, needs_review, order total (snapshot), payment reference, carrier (from first line item). Filters include the full set of operational statuses (pending_payment, paid, under_review, approved, processing, in_transit, delivered, cancelled, etc.).
- **Order detail** (admin orders show) exposes:
  - Order info: status, payment status and reference, total (snapshot), estimated, needs_review, reviewed_at, admin_notes.
  - Payments list.
  - Per-shipment: shipping method, subtotal, shipping fee; **shipping override** fields (amount, carrier, notes) with a form to apply override.
  - Per line item: name, store, price, quantity, **source_type / carrier**; expandable **product_snapshot**, **pricing_snapshot**, **missing_fields**, **estimated**, **imported_product_id**, **cart_item_id** for audit.
  - **Mark as reviewed**: form to set admin notes and mark the order as reviewed (clears needs_review, sets reviewed_at). Action is logged.
  - **Status change**: dropdown of **allowed** next statuses only (see workflow below). Change is logged.
  - **Operation log**: table of all admin actions (review, status_change, shipping_override, reprice_note) with date, action, details, admin.

Review classification is prepared for future granular states: **review_state** (JSON) on the order and constants `Order::REVIEW_STATE_NEEDS_ADMIN_REVIEW`, `REVIEW_STATE_NEEDS_REPRICE`, `REVIEW_STATE_NEEDS_SHIPPING_COMPLETION`. The UI and services can later branch on these without redesigning the flow.

---

## How payment state affects order operations

- **OrderStatusWorkflowService** defines allowed next statuses from each status. **Fulfillment statuses** (under_review, approved, processing, purchased, shipped_to_warehouse, international_shipping, in_transit, delivered) require the order to be **paid** (at least one payment with status `paid`) before the order can enter them.
- **Unpaid** orders (e.g. `pending_payment`) can only transition to `paid` (via webhook) or `cancelled`. They cannot move to processing, in_transit, delivered, etc.
- **Cancelled** is terminal: no further status changes.
- The admin status dropdown on order detail is built from **canTransitionTo**: only statuses that are allowed from the current state (and that satisfy the payment check when applicable) are shown.

---

## How review / override actions are tracked

- **order_operation_logs**: each row is `order_id`, `admin_id`, `action`, `payload` (JSON), `notes`, timestamps.
- **Actions**:
  - `status_change` — payload: `from`, `to`.
  - `review` — payload: `notes`, `review_state`; notes also in `order.admin_notes`, `order.reviewed_at` set.
  - `shipping_override` — payload: `order_shipment_id`, `shipping_override_amount`, `shipping_override_carrier`, `notes`. The shipment’s override fields are updated; original snapshot is **not** overwritten (override fields are separate).
  - `reprice_note` — audit-only note (no change to order totals).
- Override metadata is stored on **order_shipments**: `shipping_override_amount`, `shipping_override_carrier`, `shipping_override_notes`, `shipping_override_at`. Order totals are **not** silently mutated; any future use of “effective” shipping for display or reporting can combine original snapshot and override in a defined way.

---

## Implemented pieces

| Piece | Description |
|-------|-------------|
| Order list | Payment status, status, estimated, needs_review, total snapshot, payment reference, carrier columns. |
| Order detail | Snapshots (product, pricing, shipping) and metadata (missing_fields, estimated, imported_product_id, cart_item_id) per line; payment block; review form; status dropdown (allowed only); per-shipment override form; operation log. |
| Review classification | `review_state` and `admin_notes` on orders; constants for needs_admin_review, needs_reprice, needs_shipping_completion (structure only). |
| Admin actions | Mark as reviewed (PATCH orders/{order}/review); Update status (PATCH orders/{order}/status) with workflow validation; Shipping override (PATCH orders/{order}/shipping-override). |
| Workflow | OrderStatusWorkflowService: allowed transitions, payment-aware fulfillment gate, cancelled terminal. |
| Audit | OrderOperationLog model and table; log entries for status_change, review, shipping_override. |
| Tests | Admin can review; admin can update allowed statuses; invalid transition blocked; unpaid cannot move to fulfillment; paid can move to processing; cancelled cannot change; shipping override logged. |

---

## What the next task should implement

- **Tracking / fulfillment timeline**: use existing statuses and operation log to drive a simple timeline (e.g. “Paid → Under review → Approved → Processing → …”) and optional display of key events (payment completed, reviewed, shipped) for admin and/or customer.
- **FCM (or other) notifications** for payment and order events: e.g. “Order paid”, “Order status changed to shipped”, using the same status and log data.

No silent recalculation of pricing or shipping was added; override is explicit and recorded. Converted draft orders remain non-active (status `converted`); the admin order flow does not alter that.
