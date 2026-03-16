# Shipment Tracking and Fulfillment Timeline

This document describes the shipment model, timeline events, how they affect order status, and how shipping overrides connect to shipment preparation. It forms the foundation for TASK 16 and future carrier API integration.

---

## 1. Shipment model structure

### OrderShipment

Each order may have one or more **shipment** records. Shipments are created as part of order finalization (e.g. per destination or per fulfillment source). The shipment domain is independent of pricing: financial fields (totals, snapshots) are not modified by tracking operations.

**Core fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | bigint | Primary key |
| `order_id` | FK | Order this shipment belongs to |
| `carrier` | string (50) | Carrier name (e.g. DHL, FedEx) |
| `tracking_number` | string | Carrier tracking number |
| `shipment_status` | string (50) | Operational status (e.g. in_transit, delivered) |
| `estimated_delivery_at` | timestamp | Estimated delivery date |
| `shipped_at` | timestamp | When shipment was dispatched |
| `delivered_at` | timestamp | When shipment was delivered |
| `source_type` | string (50) | warehouse / direct / etc. |
| `notes` | text | Free-form notes |
| `created_at` / `updated_at` | timestamps | |

Existing fields (unchanged by this foundation) include: `country_code`, `country_label`, `shipping_method`, `subtotal`, `shipping_fee`, `eta`, and shipping override fields (`shipping_override_amount`, `shipping_override_carrier`, `shipping_override_at`, `shipping_override_notes`).

---

## 2. Shipment event timeline

### OrderShipmentEvent

Each shipment has a **timeline** of events stored in `order_shipment_events`. This structure is ready for future carrier API integration (e.g. webhooks or polling).

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | bigint | Primary key |
| `order_shipment_id` | FK | Shipment this event belongs to |
| `event_type` | string (80) | Canonical type (see below) |
| `event_label` | string | Human-readable label |
| `event_time` | timestamp | When the event occurred |
| `location` | string | Optional location |
| `payload` | JSON | Optional carrier-specific data |
| `notes` | text | Optional notes |
| `created_at` | timestamp | |

**Standard event types:**

- `created` — Shipment record created
- `packed` — Items packed
- `purchased` — Purchase order placed
- `shipped_to_warehouse` — Sent to warehouse
- `received_at_warehouse` — Received at warehouse
- `international_shipping` — In international transit
- `arrived_destination_country` — Arrived in destination country
- `out_for_delivery` — Out for delivery
- `delivered` — Delivered
- `exception` — Exception (delay, damage, etc.)

Events are appended by admin (or in the future by a carrier sync job). The `delivered` event type triggers automatic update of the shipment’s `delivered_at` and `shipment_status`, and may update the order’s status (see below).

---

## 3. How shipment events affect order status

- **Shipment created**  
  No automatic order status change. Order may be moved to “processing” (or similar) via normal admin status workflow.

- **First shipping event / tracking assigned**  
  No automatic order status change. Admins can move the order through workflow statuses (e.g. processing → purchased → shipped_to_warehouse → in_transit) as needed.

- **Delivered event**  
  When a **delivered** timeline event is added (or “Mark delivered” is used):
  1. The shipment’s `delivered_at` and `shipment_status` are set.
  2. If **all** shipments for the order have `delivered_at` set, the system checks whether the order is allowed to transition to **delivered** via `OrderStatusWorkflowService::canTransitionTo(order, delivered)`.
  3. If the transition is allowed (e.g. current status is `in_transit`), the order’s `status` is set to **delivered** and `delivered_at` is set.

**Invalid transitions are blocked:** the workflow only allows certain next statuses (e.g. `in_transit` → `delivered`). Unpaid orders cannot enter fulfillment statuses; the same rules apply when auto-moving to delivered.

---

## 4. Shipping override and shipment preparation

Shipping overrides (TASK 15) allow admins to change the shipping amount or carrier for a specific **order shipment**. Overrides are kept separate from the original quote so that:

- Financial history is not silently altered.
- Overrides are auditable and tied to the shipment.

**Behavior:**

- Override is applied to a single `OrderShipment` (identified by `order_shipment_id` in the request).
- The following are stored on the shipment: `shipping_override_amount`, `shipping_override_carrier`, `shipping_override_at`, `shipping_override_notes`.
- Every override is logged in **order_operation_logs** with action `shipping_override` and payload including:
  - `order_shipment_id`
  - `shipping_override_amount`
  - `shipping_override_carrier`
  - `notes` (override reason/notes)

Thus the override is **associated with shipment preparation**: it is recorded on the shipment and in the audit log with the shipment id, so it is traceable and can be linked to fulfillment/timeline operations (e.g. when creating or updating shipment events). No silent change to financial history occurs.

---

## 5. Admin operations and audit trail

Admin shipment operations are implemented in `ShipmentOperationService` and are logged to **order_operation_logs** with the following actions:

- `shipment_tracking_assigned` — Tracking number set
- `shipment_carrier_changed` — Carrier set/changed
- `shipment_status_updated` — Shipment status updated
- `shipment_estimated_delivery_set` — Estimated delivery date set/cleared
- `shipment_event_added` — Timeline event appended
- `shipment_delivered` — Shipment marked delivered

Each log entry includes `order_shipment_id` in the payload so all shipment actions are traceable to the order and the specific shipment.

---

## 6. Review state and future rules (prepared only)

The code is structured so that **review_state** can later influence shipment operations:

- `ShipmentOperationService::canOperateOnShipment(Order $order)` is the single gate for all shipment operations.
- Currently it returns `true`; placeholders exist for future checks, e.g.:
  - `needs_admin_review` → block shipment creation/updates until reviewed
  - `needs_reprice` → block shipment creation until reprice is resolved
  - `approved` (or equivalent) → allow shipment operations

No full review-state logic is implemented in this task; only the hook and structure are in place.

---

## 7. Next steps (TASK 17 — FCM Notifications)

Planned: FCM notifications for payment events, order events, and **shipment events** (e.g. tracking updated, out for delivery, delivered), building on this shipment and event structure.
