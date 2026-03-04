# Zayer API

Laravel API backend for the Zayer Flutter app.

## Setup

- **Path:** `F:\laragon\www\zayer`
- **PHP:** 8.2+
- **Database:** SQLite (default) or MySQL in `.env`

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=AppConfigSeeder
php artisan db:seed --class=AdminSeeder
```

## API Base URL

- Local: `http://zayer.test` or `http://127.0.0.1:8000`
- API prefix: `/api`

## Endpoints (Summary)

| Group | Endpoints |
|-------|-----------|
| **Config** | `GET /api/config/bootstrap` |
| **Auth** | `POST /api/auth/register`, `login`, `verify-otp`, `forgot-password`, `logout` |
| **Me** | `GET|PATCH /api/me`, addresses, avatar, compliance, notification-preferences, settings, sessions, change-password |
| **Cart** | `GET|POST /api/cart` or `/api/cart/items`, `PATCH|DELETE /api/cart/items/{id}`, `DELETE /api/cart` |
| **Orders** | `GET /api/orders`, `GET /api/orders/{id}` |
| **Checkout** | `GET /api/checkout/review`, `POST /api/checkout/confirm` |
| **Wallet** | `GET /api/wallet`, `GET /api/wallet/transactions`, `POST /api/wallet/top-up` |
| **Favorites** | `GET|POST /api/favorites`, `DELETE /api/favorites/{id}` |
| **Support** | `GET /api/support/tickets`, `GET /api/support/tickets/{id}`, `POST /api/support/tickets/{id}/messages`, `POST /api/support/requests` |
| **Notifications** | `GET /api/notifications`, `PATCH /api/notifications/{id}/read` |
| **Warehouses** | `GET /api/warehouses` |
| **Product Import** | `POST /api/products/import-from-url` |

Most endpoints require `Authorization: Bearer {token}` (Sanctum).

## Admin Panel

- **URL:** `http://zayer.test/admin` or `http://127.0.0.1:8000/admin`
- **Login:** `admin@zayer.com` / `password` (change in production)

Placeholder dashboard. To use Vuexy v10.11.1:
- Copy Vuexy HTML (Bootstrap 5, vertical-menu) into `public/admin/`
- Or replace `resources/views/admin/*` with Vuexy Blade layouts

## Flutter Integration

In the Flutter app, set `API_BASE_URL` (e.g. in `lib/core/network/api_config.dart`) to your Laravel URL and replace mock repositories with HTTP calls to these endpoints.
