# Backend Final Polish Report — Eshterely

**Date:** March 2026  
**Scope:** Laravel backend and admin panel only. No Flutter changes. No full UI redesign. Launch-readiness, consistency, and missing polish.

---

## 1. Admin locale / sidebar issues found

### Locale
- **Admin locale switching:** `set-locale` was outside `auth:admin`; guests could set `admin_locale` session. Switched to authenticated-only so only logged-in admins can change locale and session persists reliably.
- **Menu label:** One hardcoded Arabic label in sidebar: "إعدادات التطبيق (API)" for App config. Replaced with `__('admin.app_config')` and key added to both en/ar.
- **Missing translation keys:** Shipping zones/rates views and order detail view used many `__('admin.*')` keys that did not exist in `lang/en/admin.php` or `lang/ar/admin.php`. Dashboard was largely hardcoded in Arabic and did not respect locale.
- **Dashboard:** Title, main KPI cards, review indicators, and payment status card header now use `__()` so dashboard respects current admin locale (ar/en).

### Sidebar / navigation
- **Missing entries:** Shipping Zones and Shipping Rates (under Config) were implemented and routable but not linked in the sidebar. Both are now in the Config submenu after "Shipping (Calculation)" so zones and rates are discoverable.
- **Grouping:** Config already contained Theme, Splash, Onboarding, Markets, Featured stores, Promo banners, Warehouses, App config, and Shipping settings. Shipping Zones and Shipping Rates were added in the same Config block for a single, logical shipping-config group (settings → zones → rates).

---

## 2. Fixes implemented

### Part 1 — Admin language / locale
- Moved `set-locale/{lang}` route inside `auth:admin` so only authenticated admins can set locale; avoids guest session pollution and ensures switching works after login.
- Replaced hardcoded "إعدادات التطبيق (API)" in sidebar with `__('admin.app_config')`; added `app_config` to en and ar.
- Added 80+ missing keys to `lang/en/admin.php` and `lang/ar/admin.php` for: shipping zones/rates (carrier, zone, pricing_mode, base_rate, active, notes, add_new, are_you_sure, etc.), order detail (order_info, payment, payments, paid, failed, provider, shipment, tracking, price_lines, operation_log, etc.), review_state_* for dashboard, dashboard card labels and help text, and generic labels (saved_successfully, deleted_successfully).
- Dashboard: section title and all main KPI cards (imported products, confirmed, active carts, draft orders, orders pending payment/paid/needing review/in fulfillment/delivered, shipments in transit, failed payments, successful notifications) now use `__()`. Review indicators block and payment status card header use translations. Fallback for unknown `review_state_*` keys shows the raw state key if translation is missing.

### Part 2 & 3 — Sidebar completeness and grouping
- Added sidebar links under Config: **Shipping zones** (`admin.config.shipping-zones.index`) and **Shipping rates** (`admin.config.shipping-rates.index`) after Shipping (Calculation), with translated labels.
- No duplicate or confusing entries added; structure remains: Dashboard → Content & Config (theme, splash, onboarding, markets, featured stores, promo banners, warehouses, app config, shipping settings, shipping zones, shipping rates) → Management (users, orders, cart review, wallets, support) → Send notification.

### Part 4 — Admin payment visibility
- **Order show:** Payments card now shows per-payment: reference, status (with badge: paid=success, failed=danger, else secondary), provider when present, amount/currency and paid date. For failed payments, failure_code and failure_message are shown.
- **Attempts/events:** Order controller loads `payments.attempts` and `payments.events`. Order show view adds a collapsible `<details>` per payment listing last 5 attempts (time + status) and last 5 events (time + event_type + source) for troubleshooting.
- Order detail labels (Order info, Payment, Payments, Status, Mark reviewed, Send push, Shipment, Tracking, etc.) switched to `__()` for locale consistency.

### Part 5 — Audit / log completion
- **Shipping settings:** Already audited via `ShippingSettingAudit` and existing controller logic; no change.
- **Shipping zones:** `Log::info('Admin shipping zone created', [...])` on store; `Log::info('Admin shipping zone updated', [...])` on update; `Log::info('Admin shipping zone deleted', [...])` on destroy (with zone_id, carrier, admin_id).
- **Shipping rates:** Same pattern: `Log::info('Admin shipping rate created|updated|deleted', [...])` with rate_id, carrier, admin_id (and zone_code on create).
- Order status changes, review decisions, shipping overrides, and shipment updates were already logged via `OrderOperationLog` and `ShipmentOperationService`. Payment status updates and webhook events remain in `payment_events` and existing payment/attempt logging; no duplicate order-level audit added.

### Part 6 — Status consistency
- No structural change to order/payment/shipment lifecycles. Order show uses existing status values and shows payment status (paid/pending/failed) and provider; labels are translated. Status values themselves are unchanged.

### Part 7 — Security / API placeholders
- No fake 2FA or session-management features added or exposed. User show uses existing `user_sessions` and addresses from the backend; no misleading “recent activity” or unsupported security UI added.

### Part 8 — Settings / defaults
- No change to app-level defaults (language, currency, warehouse, notification prefs). Existing config and API contracts left as-is.

### Part 9 — Launch-risk cleanup
- **deploy_once.php:** Already guarded: token check, `production` and `debug` checks prevent execution in production. Documented in report; recommend removing or further restricting (e.g. env-based token) before go-live if the script is no longer needed.
- No temporary scripts activated unsafely, no debug-only behavior left in admin flows, no stale admin endpoints removed (all in use). Order and shipment controllers already use validation and ownership checks.

### Part 10 — Tests
- **AdminLocaleAndMenuTest:** Added. Covers: set-locale requires admin auth (guest redirected to login); authenticated admin can set locale and session is set; invalid lang is ignored; dashboard response includes sidebar links for shipping-zones and shipping-rates.

---

## 3. Deferred / minor issues

- **Dashboard:** Payment status card body (pending, requires_action, processing, paid, failed, cancelled/refunded labels) and “Recent payment attempts” / “Latest gateway attempts” labels, and the “Shipping & fulfillment” card and its list labels, remain hardcoded in Arabic. Can be moved to `__()` in a follow-up if full dashboard locale parity is required.
- **User show:** Sessions tab uses `user_sessions` from DB; if this table is not populated by the app, the tab may often be empty. No code change; consider documenting that sessions are app-driven.
- **Status labels:** Order status dropdown and shipment status still show raw values (e.g. `pending_payment`, `in_transit`). Translating these with `admin.order_status_*` / `admin.shipment_status_*` was not added to keep the diff small; can be added later for consistency.

---

## 4. Final backend / admin launch-readiness assessment

- **Locale:** Admin locale switching is reliable and authenticated; main admin pages (dashboard, order show, shipping zones/rates, menu) respect locale. Missing keys added for en/ar.
- **Navigation:** All major implemented modules (dashboard, users, orders, cart review, wallets, support, notifications, shipping settings, shipping zones, shipping rates, app config, content config) are reachable from the sidebar in a logical order.
- **Payments:** Order detail shows payment status, provider, failure info, and attempts/events for troubleshooting without building a separate payment console.
- **Audit:** Order/shipment operations continue to use `OrderOperationLog`; shipping settings use `ShippingSettingAudit`; shipping zones/rates changes are logged to application log with admin and entity context.
- **Risk:** No new launch risks introduced. deploy_once.php remains safe in production; no debug code or unsafe scripts enabled.

**Conclusion:** Backend and admin are in a launch-ready state for the scope of this polish pass. Remaining items are optional (extra dashboard translations, status label translation) and can be done in a follow-up.
