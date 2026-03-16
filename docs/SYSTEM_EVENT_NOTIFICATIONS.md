# System Event Notifications

This document describes how the Laravel backend automatically triggers Firebase (FCM) push notifications for core system events, how payloads are structured, and how mobile apps can deep-link from them.

---

## 1. Events that trigger automatic notifications

### 1.1 Order created

- **When**: A new `Order` row is created (e.g. checkout, draft order finalization).
- **Trigger**: `OrderObserver::created()` → `OrderShipmentNotificationTrigger::onOrderCreated()`.
- **Recipient**: The order owner (`order.user`).
- **Title**: `Order Created`
- **Body**: `Your order has been successfully created.`
- **Type**: `order_created`
- **Reference ID**: `order_id`

### 1.2 Order status changes

These are fired when admins change order status via `AdminOrderOperationService::updateStatus()`:

- **processing**
  - Trigger: `onOrderProcessing()`
  - Title: `Order Update`
  - Body: `Your order is now being processed.`
- **shipped / in_transit**
  - Triggers:
    - `STATUS_SHIPPED_TO_WAREHOUSE` or `STATUS_INTERNATIONAL_SHIPPING` → `onOrderShipped()`
    - `STATUS_IN_TRANSIT` → `onOrderInTransit()`
  - Titles/Bodies: generic **Order Update** variations (e.g. “Your order has been shipped.”, “Your order is on the way.”).
- **delivered**
  - Trigger: `onOrderDelivered()`
  - Title: `Order Update`
  - Body: `Your order has been delivered.`
- **cancelled**
  - Trigger: `onOrderCancelled()`
  - Title: `Order Update`
  - Body: `Your order has been cancelled.`

All of these use **type** `order_status` and **reference_id** = `order_id`.

### 1.3 Payment status changes

Handled in `SquareWebhookService` using `OrderShipmentNotificationTrigger`:

- **Payment successful**
  - When: Webhook normalizes status to `PaymentStatus::Paid` and syncs the order.
  - Trigger: `onPaymentSuccess(Payment $payment)`
  - Title: `Payment Successful`
  - Body: `Your order :order_number has been paid successfully.`
  - Type: `payment_success`
  - Reference ID: related `order_id`

- **Payment failed**
  - When: Webhook normalizes status to `PaymentStatus::Failed`.
  - Trigger: `onPaymentFailed(Payment $payment)`
  - Title: `Payment Failed`
  - Body: `Your payment for order :order_number could not be completed.`
  - Type: `payment_failed`
  - Reference ID: related `order_id`

### 1.4 Shipment tracking assigned

- **When**: Admin assigns a tracking number via `ShipmentOperationService::assignTrackingNumber()`.
- **Trigger**: `onShipmentTrackingAssigned(OrderShipment $shipment)`
- **Recipient**: The order owner.
- **Title**: `Shipment Update`
- **Body**: `Your shipment is now on the way.`
- **Type**: `shipment_tracking`
- **Reference ID**: `shipment_id`

### 1.5 Shipment delivered

- **When**: Shipment is marked delivered (via timeline event or direct mark-delivered).
- **Trigger**: `onShipmentDelivered(OrderShipment $shipment)`
- **Recipient**: The order owner.
- **Title**: `Order delivered`
- **Body**: `Your order :order_number has been delivered.`
- **Type**: `shipment_delivered`
- **Reference ID**: `order_id`

### 1.6 Order review completion

- **When**: Admin marks an order as reviewed via `AdminOrderOperationService::markAsReviewed()`.
- **Trigger**: `onReviewCompleted(Order $order)`
- **Recipient**: The order owner.
- **Title**: `Order Review Complete`
- **Body**: `Your order review has been finalized. Shipping estimate and details are ready.`
- **Type**: `review_completed`
- **Reference ID**: `order_id`

---

## 2. Payload structure

All automatic system notifications are sent through `NotificationDispatchService::sendSystemEvent()`, which:

- logs a `notification_dispatches` record with:
  - `type = system_event`
  - `user_id`, `order_id`, `shipment_id` (when applicable)
  - `title`, `body`
  - `meta` JSON (event name, status, payment/shipment IDs, etc.)
- sends an FCM message via `FcmNotificationService`.

The **FCM data payload** produced by `FcmNotificationService::systemEventData()` always includes:

- **title** – same as notification title (string)
- **body** – same as notification body (string)
- **type** – logical event type, e.g.:
  - `order_created`
  - `order_status`
  - `payment_success`
  - `payment_failed`
  - `shipment_tracking`
  - `shipment_delivered`
  - `review_completed`
- **reference_id** – ID of the main entity:
  - Order-related events: `order_id`
  - Shipment-related events: `shipment_id`

It can also include optional **deep-link fields**:

- **target_type** – e.g. `order` or `shipment`
- **target_id** – corresponding ID as a string
- **route_key** – e.g. `order_detail`
- **payload** – JSON-encoded extra meta (for example `{\"order_id\":123,\"shipment_id\":5}`).

All keys and values are sent as **strings**, as required by FCM data payloads.

---

## 3. Deep linking on the client

The backend does **not** control Flutter navigation; it only prepares the data payload so that the app can deep-link later.

A typical client-side flow:

1. Receive an FCM message in the Flutter app (background or foreground).
2. Inspect the `data` fields:
   - `type` – decide which screen to open (e.g. order detail vs. shipment tracking).
   - `reference_id` – the primary ID to fetch.
   - `target_type`, `target_id`, `route_key` – optional routing hints.
   - `payload` – parse as JSON for extra context.
3. Navigate to the appropriate screen, e.g.:
   - If `type` is `order_created`, `order_status`, `payment_success`, `payment_failed`, or `review_completed`:
     - Open the **Order detail** screen for `reference_id` / `target_id`.
   - If `type` is `shipment_tracking`:
     - Open an **Order detail** or **Shipment detail** tab, using `shipment_id` from `payload` or `reference_id`.
   - If `type` is `shipment_delivered`:
     - Open the **Order detail** screen and highlight the delivered shipment.

By standardizing on `type` and `reference_id`, plus optional deep-link hints, the mobile app can handle future events without backend changes to the overall pattern.

