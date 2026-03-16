## Admin Shipping Configuration – Overview

This document summarizes the admin-manageable pieces of the shipping engine and how to operate them.

### 1. Calculation Settings (single form)

Managed under `Admin → Config → Shipping (Calculation)`:

- **Volumetric divisor**: `volumetric_divisor`
- **Default currency**: `default_currency`
- **Default markup %**: `default_markup_percent`
- **Minimum shipping charge**: `min_shipping_charge`
- **Warehouse handling fee**: `warehouse_handling_fee`
- **Multi‑package extra %**: `multi_package_percent`
- **Carrier discounts**: `carrier_discount_dhl`, `carrier_discount_ups`, `carrier_discount_fedex`
- **Rounding strategy**: `rounding_strategy` (`none`, `nearest_kg`, `up_to_500g`)
- **Final pricing layer**: `service_fee`, `platform_markup_percent`, `minimum_order_fee`, `minimum_order_threshold`
- **Fallback package defaults** (used when products are missing data):
  - `shipping_default_weight` / `shipping_default_weight_unit` (`kg` or `lb`)
  - `shipping_default_length`, `shipping_default_width`, `shipping_default_height`
  - `shipping_default_dimension_unit` (`cm` or `in`)

All values are validated (`numeric|min:0` where appropriate, `in:` lists for enums) and read by `ShippingPricingConfigService`, which is the single source of truth for the quote engine.

### 2. Carrier Zone and Rate Tables

Managed under:

- `Admin → Config → Shipping Zones`
- `Admin → Config → Shipping Rates`

**Zones** (`shipping_carrier_zones`):

- Carrier (`dhl`, `ups`, `fedex`)
- Origin country (optional; 2‑letter ISO)
- Destination country (2‑letter ISO)
- Zone code (free text, e.g. `Z1`, `GCC-A`)
- Active flag
- Optional notes

**Rates** (`shipping_carrier_rates`):

- Carrier
- Zone code (links to a zone)
- Pricing mode: `direct` or `warehouse`
- Weight range in kg: `weight_min_kg` → `weight_max_kg` (open‑ended if `weight_max_kg` is empty)
- Base rate (in the configured currency)
- Active flag
- Optional notes

The `ShippingZoneRepository` reads these tables and is used by the carrier pricing resolvers so DHL/UPS/FedEx calculations can use real base rates instead of hardcoded formulas.

### 3. Warehouse vs Direct Visibility

- Each carrier rate has a **pricing mode** of `direct` or `warehouse`.
- Admins can filter rates by pricing mode from the `Shipping Rates` index.
- In the quote breakdown, the `pricing_mode` field (e.g. `dhl_zone`) combined with the chosen rate’s `pricing_mode` indicates whether a **warehouse** or **direct** schedule was used.

### 4. Fallback Package Defaults

When imported products do not contain complete shipping data, `ProductToShippingInputMapper` uses:

- `shipping_default_weight` / `shipping_default_weight_unit`
- `shipping_default_length`, `shipping_default_width`, `shipping_default_height`
- `shipping_default_dimension_unit`

Changing these values in the Shipping Calculation settings immediately affects how missing product data is filled in before quotes are computed.

### 5. Auditability

- Every change to a shipping calculation setting is recorded in `shipping_setting_audits` with:
  - `key`, `old_value`, `new_value`, `admin_id`, and timestamp.
- Carrier zones and rates store `created_by_admin_id` / `updated_by_admin_id` and timestamps for traceability.

### 6. Next Suggested Task

1. **Audit logs for critical operations**: Extend auditing beyond config (e.g. order status changes, manual shipping overrides, wallet adjustments) with lightweight admin‑visible logs.
2. **Order status consistency polish**: Ensure backend, app, and admin share a clean, documented status model and transitions for orders and shipments.

