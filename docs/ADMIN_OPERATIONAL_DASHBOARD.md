# Admin Operational Dashboard

This document summarizes the operational metrics surfaced on the admin dashboard and how they are structured in the backend.

---

## What the dashboard shows

The admin dashboard now focuses on operational visibility across the full lifecycle:

- **Imported products & carts**
  - Total imported products and confirmed imported products (added to cart or ordered).
  - Active carts (cart items not yet attached to a draft order).
  - Draft orders count.

- **Orders & fulfillment**
  - Orders by key operational states:
    - `pending_payment`, `paid`, fulfillment states (under_review, approved, processing, purchased, shipped_to_warehouse, international_shipping, in_transit), and `delivered`.
  - Orders needing review (`needs_review = true`).
  - Orders in fulfillment and delivered orders.
  - Estimated orders counts and draft orders blocked from checkout (`needs_review = true` on draft orders).
  - Orders awaiting admin action (combines `needs_review` with problematic payment states such as requires_action / failed / cancelled).

- **Payments**
  - Payment counts by `PaymentStatus`:
    - pending, requires_action, processing, paid, failed, cancelled, refunded.
  - Recent payments list (amount, status, order, created_at).
  - Recent payment attempts (from `payment_attempts`).
  - Recent webhook-updated successful payments (from `payment_events` with `event_type = webhook_success`).

- **Shipments & fulfillment**
  - Shipments grouped by `shipment_status`.
  - Orders with tracking numbers assigned.
  - Delivered shipments.
  - Shipments with exceptions (status_tags contains `exception` or `shipment_status = exception`).
  - Top carriers (by shipment count).
  - Top destination countries.

- **Notifications**
  - Notification dispatch counts by type:
    - bulk, individual, system_event.
  - Notification dispatch counts by send status:
    - pending, sent, partial, failed.
  - Recent notification dispatches with title, type, status and time.

- **Top entities & recent activity**
  - Top destination countries, carriers, and order statuses (by count).
  - Recent operational activity feed:
    - Recent orders (status, amount, created_at).
    - Recent shipments (status, carrier, destination).
    - Recent payments (amount, status).
    - Recent notifications.

---

## Backend structure

- **Service layer**
  - `App\Services\Admin\AdminDashboardMetricsService` aggregates all dashboard data:
    - `summary()` – high‑level counts (products, carts, draft orders, key order states, shipments, payment failures, notification success/failed).
    - `review()` – review indicators and `review_state` distribution on orders.
    - `payments()` – payment counts, recent payments, attempts, and webhook events.
    - `shipments()` – shipment status distribution, tracking, delivered, exceptions, top carriers and countries.
    - `notifications()` – notification counts by type/status and recent dispatches.
    - `topEntitiesMetrics()` – top destination countries, carriers, and order statuses.
    - `recentActivity()` – short activity feed for orders, shipments, payments, and notifications.

- **Controller**
  - `App\Http\Controllers\Admin\DashboardController@index` now resolves `AdminDashboardMetricsService`, calls `getDashboardData()`, and passes the result directly to the Blade view:
    - Keys: `summary`, `review`, `payments`, `shipments`, `notifications`, `top`, `recent_activity`.

- **Blade view**
  - `resources/views/admin/dashboard.blade.php` renders:
    - Summary metric cards at the top (products, carts, draft orders, payment and fulfillment highlights, notifications).
    - A review card with counts and `review_state` distribution prepared for future granular review states.
    - Payment status mini‑grid plus tables for recent payments and attempts.
    - Shipment card with status distribution, exception counts, and top carriers.
    - Notification card with breakdown by type/status and a recent dispatch list.
    - Top‑entities card (countries, carriers, order statuses).
    - Recent operational activity card (orders, shipments, payments, notifications).

---

## Next tasks to implement

1. **Security/settings cleanup and hardening**
   - Tighten admin access control to dashboard metrics (e.g. ensure proper guards, policies, and rate limiting for any JSON endpoints added later).
   - Review environment and payment/webhook configuration exposure on the admin side.
   - Add basic health indicators (webhook health, queue health) without exposing sensitive internals.

2. **Advanced admin reporting refinements**
   - Add time‑window filters (today, last 7 days, last 30 days) to the dashboard metrics.
   - Introduce simple trend indicators (percentage change vs previous period) for orders, payments, and shipments.
   - Extend review metrics to break down `review_state` in more detail once granular states are in active use.
   - Add CSV/Excel exports for selected aggregated views if needed by operations.

