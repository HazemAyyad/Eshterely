# Security & Settings Hardening (Backend)

Date: 2026-03-16  
Scope: Laravel backend only (no Flutter UI redesign)

## What was hardened

### Account security
- Password change (`POST /api/me/change-password`) now revokes **other** active Sanctum tokens after a successful password change (keeps the current session).

### Active sessions / device visibility
- Sessions endpoint (`GET /api/me/sessions`) now returns **real active sessions** based on Sanctum tokens instead of placeholder data.
- Session revoke endpoint (`DELETE /api/me/sessions/{id}`) now revokes the specified Sanctum token (and blocks revoking the current token via this endpoint to avoid accidental lock-out).

### Settings synchronization
- Settings endpoint (`GET/PATCH /api/me/settings`) now:
  - normalizes `language_code` (`en` / `ar`)
  - normalizes `currency_code` casing
  - uses consistent defaults and returns a stable shape
  - persists updates without rewriting `created_at` on every patch

### Notification preferences synchronization
- Notification preferences endpoint (`GET/PATCH /api/me/notification-preferences`) now:
  - exposes the full persisted preference set (including flags present in the migration but previously not returned)
  - validates quiet-hours format (`HH:MM`)
  - persists updates without rewriting `created_at` on every patch

### Operational status clarity
- `OrderResource` now exposes `status_key` alongside the existing `status` to provide a **frontend-friendly** stable status bucket (e.g. `pending_review`, `pending_payment`, `processing`, `shipped`, `delivered`, `cancelled`) without changing the internal workflow/status values.

## Placeholder-like behavior removed
- The compliance endpoint (`GET /api/me/compliance`) no longer returns hardcoded “action required” placeholder data. It now returns a neutral, non-misleading default response until a real compliance module exists.

## Tests added
- Settings defaults + normalization + persistence
- Notification preferences full-shape defaults + persistence
- Sessions listing/revocation backed by Sanctum tokens
- Order resource `status_key` exposure

## Future work (not implemented here)
- Persist richer session metadata (user agent / IP / device name) per token for improved device visibility.
- Add a security events log (password change, login, session revoke, etc.) for a full “recent activity” view.
- Introduce a centralized settings resource/DTO layer if more settings domains expand (privacy toggles, regional compliance, etc.).

