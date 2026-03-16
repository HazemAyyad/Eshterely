# FCM Notifications System

This document describes the Firebase Cloud Messaging (FCM) notification layer for Eshterely: token management, event-based notifications, admin bulk/individual sending, and logging/auditability.

---

## 1. Token management model

### user_device_tokens

Each user can have **multiple active device tokens**. Fields:

| Field | Description |
|-------|-------------|
| `user_id` | Owner |
| `fcm_token` | FCM registration token (unique per user+token) |
| `device_type` | Legacy / client-sent (e.g. android, ios) |
| `platform` | Normalized: android, ios, web, unknown |
| `device_name` | Optional device label |
| `app_version` | Optional app version |
| `last_seen_at` | Last time token was updated/used |
| `is_active` | If false, token is not used for sending (e.g. after FCM reports invalid) |
| `created_at` / `updated_at` | |

- **Upsert:** When the app sends a token (e.g. on login or `PATCH /api/me/fcm-token`), `DeviceTokenService::upsertToken()` creates or updates the row and sets `is_active = true`, `last_seen_at = now()`.
- **Invalid tokens:** When FCM returns invalid/unknown for a token, the service deactivates it (`is_active = false`) so it is no longer used.
- **Backward compatibility:** The existing `updateFcmToken` (Me) and login/verify OTP flows use `DeviceTokenService`; `device_type` is still accepted and mapped to `platform`.

---

## 2. Event-based notification flow

Lifecycle events trigger FCM (and a logged dispatch) via **OrderShipmentNotificationTrigger**:

| Event | When | Title/body (from `lang/en/notifications.php`) |
|-------|------|-----------------------------------------------|
| **Payment successful** | Square webhook sets payment to paid and order to paid | Payment successful / Your order … has been paid successfully. |
| **Order processing** | Admin changes order status to `processing` | Order processing / Order … is now being processed. |
| **Shipment tracking** | Admin assigns tracking number on a shipment | Shipment tracking / Tracking for order … : … |
| **Shipment delivered** | Admin marks shipment delivered or adds “delivered” timeline event | Order delivered / Your order … has been delivered. |

- Trigger is called from: `SquareWebhookService` (after payment paid), `AdminOrderOperationService` (status → processing), `ShipmentOperationService` (assignTrackingNumber, markDelivered, appendEvent with type `delivered`).
- Each trigger call uses **real** order/payment/shipment state (no duplicate or fake state).
- Notifications are sent to the **order’s user** and a **notification_dispatch** row is created with type `system_event`, linked to `user_id`, `order_id`, `shipment_id` as applicable.

---

## 3. Bulk vs individual admin sending

### Bulk (Notifications > Send)

- **In-app notifications:** Creating in-app `Notification` records (title, subtitle, type, action_route, etc.) is unchanged.
- **FCM (optional):** If “Send push (FCM) also” is checked, the backend:
  - Sends FCM to the same set of users (all users or selected user IDs).
  - Uses title, body (from subtitle or title), optional image URL, optional deep-link meta.
  - Creates a **notification_dispatch** record with type `bulk`, `created_by` = admin, and target_scope `all_users` or `user_ids`.

### Individual

- **From user detail:** “Send push (FCM)” form sends to that user only; creates a dispatch with type `individual`, `user_id`, `created_by`.
- **From order detail:** “Send push to customer” sends to the order’s user; creates a dispatch with type `individual` and optional order-related meta (e.g. `target_type=order`, `target_id`, `route_key=order_detail`).

---

## 4. Notification logging / auditability

### notification_dispatches

Every FCM send (bulk, individual, or system event) is recorded:

| Field | Description |
|-------|-------------|
| `type` | bulk \| individual \| system_event |
| `title` | Notification title |
| `body` | Notification body |
| `target_scope` | all_users \| user_ids \| tokens |
| `user_id` | When targeting a single user |
| `order_id` / `shipment_id` | When tied to order/shipment (e.g. system events) |
| `send_status` | pending \| sent \| partial \| failed |
| `provider_response_summary` | Short summary (e.g. sent=2, failed=0 or “FCM not configured”) |
| `created_by` | Admin ID for bulk/individual |
| `meta` | JSON for deep-link (target_type, target_id, route_key, etc.) |
| `created_at` / `updated_at` | |

- Dispatches are **traceable** to user, order, shipment, and admin where applicable.
- System events always set `order_id` / `shipment_id` when relevant.

---

## 5. Deep-link readiness (backend payload)

FCM **data** payload is prepared for future Flutter deep-link handling. Optional fields:

- **target_type** — e.g. `order`, `shipment`, `user`
- **target_id** — ID of the entity
- **route_key** — e.g. `order_detail`
- **payload** — JSON string of extra meta

These are set in `FcmNotificationService::dataPayload()` and in the trigger for system events. **No Flutter deep-link handling is implemented in this task;** the backend only prepares the payload.

---

## 6. Configuration

- **FIREBASE_CREDENTIALS:** Path to the Firebase service account JSON file. If empty or file missing, FCM sending is skipped and dispatches record a “FCM not configured”–style summary.
- **config/firebase.php:** Reads `FIREBASE_CREDENTIALS`; Laravel binds `Kreait\Firebase\Contract\Messaging` only when the credentials file exists.

---

## 7. Next steps (suggested)

- **Flutter:** Handle FCM on the client (foreground/background), parse `data` (target_type, target_id, route_key, payload) and implement deep navigation (e.g. open order detail).
- **Admin:** Optional “Notification history” page listing `notification_dispatches` with filters (type, user, order, date, status).
- **Operational:** Optional admin dashboard widgets for recent dispatches or failure counts.
