## Initial Shipping Seed Data

This document describes the initial shipping configuration seed data shipped with the backend.  
The goal is to make the admin shipping pages usable immediately after migrations and `php artisan db:seed`.

### 1. Seeders Added

- `ShippingSettingsSeeder`
  - Populates `shipping_settings` with operational starter values for:
    - `volumetric_divisor`
    - `default_currency`
    - `default_markup_percent`
    - `min_shipping_charge`
    - `warehouse_handling_fee`
    - `multi_package_percent`
    - `carrier_discount_dhl`, `carrier_discount_ups`, `carrier_discount_fedex`
    - `rounding_strategy`
    - `service_fee`, `platform_markup_percent`, `minimum_order_fee`, `minimum_order_threshold`
    - Fallback package defaults:
      - `shipping_default_weight`, `shipping_default_weight_unit`
      - `shipping_default_length`, `shipping_default_width`, `shipping_default_height`
      - `shipping_default_dimension_unit`
- `ShippingCarrierZonesSeeder`
  - Populates `shipping_carrier_zones` with starter zones for:
    - DHL / UPS / FEDEX
    - **Regions** (by destination country ISO code):
      - `ME_LEVANT` – Palestine, Jordan, Lebanon, Syria
      - `ME_GULF` – Saudi Arabia, UAE, Kuwait, Qatar, Bahrain, Oman
      - `EU_STANDARD` – a small starter set of EU countries (DE, FR, IT, ES, NL, BE, SE, NO, DK)
      - `NA_US_CA` – United States, Canada
- `ShippingCarrierRatesSeeder`
  - Populates `shipping_carrier_rates` with weight-based base rates for:
    - DHL / UPS / FEDEX
    - Zones: `ME_LEVANT`, `ME_GULF`, `EU_STANDARD`, `NA_US_CA`
    - Pricing modes: `direct` and `warehouse`
    - Weight bands (kg): `[0–0.5]`, `[0.5–1]`, `[1–3]`, `[3–5]`, `[5–10]`, `[10–20]`, `[20+ (open-ended)]`
- `ShippingConfigurationSeeder`
  - Master seeder that runs:
    - `ShippingSettingsSeeder`
    - `ShippingCarrierZonesSeeder`
    - `ShippingCarrierRatesSeeder`

`DatabaseSeeder` is configured to call `ShippingConfigurationSeeder`, so all shipping data is seeded as part of the normal `db:seed` flow.

### 2. Tables Populated

- `shipping_settings`
  - One row per key (`key` / `value`) for all shipping calculation settings used by `ShippingPricingConfigService`.
- `shipping_carrier_zones`
  - Multiple rows per carrier / zone code, one per destination country.
  - Fields: `carrier`, `origin_country` (null in seed), `destination_country`, `zone_code`, `active`, `notes`.
- `shipping_carrier_rates`
  - Multiple rows per carrier / zone / pricing mode, one per weight band.
  - Fields: `carrier`, `zone_code`, `pricing_mode` (`direct` / `warehouse`), `weight_min_kg`, `weight_max_kg`, `base_rate`, `active`, `notes`.

### 3. Starter Default Values (Operations Should Review)

The following values are **starter defaults only** and are expected to be reviewed and adjusted by operations before production:

- `default_markup_percent` – seeded to **5%**
- `min_shipping_charge` – seeded to **5** (in `default_currency`)
- `warehouse_handling_fee` – seeded to **3**
- `multi_package_percent` – seeded to **10%**
- Carrier discounts:
  - `carrier_discount_dhl` – **5%**
  - `carrier_discount_ups` – **3%**
  - `carrier_discount_fedex` – **4%**
- Final pricing layer:
  - `service_fee` – **1.5**
  - `platform_markup_percent` – **3%**
- Fallback package defaults (used when products are missing dimensions/weight):
  - `shipping_default_weight` – **0.5 kg**
  - `shipping_default_length` / `width` / `height` – **20 × 15 × 8 cm**

All **zone and rate values** in `shipping_carrier_zones` and `shipping_carrier_rates` are also **operational examples**, not contractual tariffs. They are intended to make the admin UI and quote engine usable on day one, but must be validated and tuned for real carrier contracts.

### 4. Admin Visibility After Seeding

After running migrations and:

```bash
php artisan db:seed
```

the following admin pages will contain usable data:

- `Admin → Config → Shipping (Calculation)`  
  - Backed by `shipping_settings` via `ShippingPricingConfigService`.
- `Admin → Config → Shipping Zones`  
  - Lists and edits `shipping_carrier_zones`.
- `Admin → Config → Shipping Rates`  
  - Lists and edits `shipping_carrier_rates` (both `direct` and `warehouse` pricing modes).

### 5. Rerunning Seeders (Idempotency)

- All shipping setting keys are inserted **only if missing**; existing values are not overwritten.
- Carrier zones and rates are created using `updateOrCreate` keyed by:
  - Zones: `carrier` + `destination_country` + `zone_code`
  - Rates: `carrier` + `zone_code` + `pricing_mode` + `weight_min_kg` + `weight_max_kg`

This makes it safe to rerun the shipping seeders on development and staging without creating duplicate rows.

