# Shipping Engine – Foundation Summary

This document describes the first clean foundation for the Eshterely shipping engine: a structured, admin-configurable service layer that refactors concepts from legacy shipping helpers without copying legacy files.

## What Was Extracted (Conceptually) from Legacy Helpers

The following concepts were implemented based on typical DHL/UPS/FedEx and warehouse logic found in legacy shipping helpers:

- **Conversion logic** – Centralized kg ↔ lb and cm ↔ in so all calculations use canonical units (kg, cm).
- **Volumetric rules** – Volumetric weight = (L × W × H) / divisor, with divisor configurable per carrier/region (e.g. 5000 for cm/kg).
- **Chargeable weight** – Chargeable weight = max(actual weight, volumetric weight); used as the billing weight.
- **Pricing adjustment patterns** – Minimum charge, warehouse handling fee, default markup %, multi-package percentage, and carrier discount percentages as configurable inputs to the quote (not hardcoded).
- **Warehouse vs non-warehouse** – A boolean “warehouse mode” drives handling fee and future zone/carrier logic.

Legacy helper files were **not** copied or depended on; they were used only as business-logic reference. No controller/view behavior from the old system was preserved.

## Reusable Calculations

| Component | Purpose |
|-----------|---------|
| `WeightConverter` | kg ↔ lb (2.20462 lb per kg). |
| `DimensionConverter` | cm ↔ in (2.54 cm per in). |
| `VolumetricWeightCalculator` | Volumetric weight from L/W/H (cm) and divisor; chargeable weight = max(actual, volumetric). |
| `PackageNormalizer` | Raw input → normalized package (destination, carrier, warehouse mode, weight in kg, dimensions in cm, quantity). |
| `ShippingPricingConfigService` | Reads all pricing-related and operational values from admin settings with typed fallbacks. |

All of the above are used by `ShippingQuoteService::quote()` to produce a structured `ShippingQuoteResult`.

## Admin-Configurable Values

These values are stored in the `shipping_settings` table and managed from **Admin → Content & Config → Shipping (Calculation)**. They are **not** hardcoded in application code:

| Key | Description | Default / fallback |
|-----|-------------|--------------------|
| `volumetric_divisor` | Divisor for volumetric weight (e.g. 5000 for cm/kg). | 5000 |
| `default_currency` | Currency for quotes. | USD |
| `default_markup_percent` | Default markup percentage. | 0 |
| `min_shipping_charge` | Minimum shipping amount. | 0 |
| `warehouse_handling_fee` | Extra fee when warehouse mode is on. | 0 |
| `multi_package_percent` | Extra percentage for multi-package. | 0 |
| `carrier_discount_dhl` / `_ups` / `_fedex` | Carrier discount percentages. | 0 |
| `rounding_strategy` | Weight rounding: none, nearest_kg, up_to_500g. | nearest_kg |

The foundation reads these via `ShippingPricingConfigService`. Missing or invalid values use the documented fallbacks so the engine behaves safely without full config.

## How the Foundation Reads These Values

- **Storage**: `shipping_settings` table (key/value).
- **Model**: `App\Models\ShippingSetting` with `getValue(key)`, `setValue(key, value)`, `getAllAsMap()`, and short-lived cache.
- **Service**: `ShippingPricingConfigService` exposes typed getters (e.g. `volumetricDivisor()`, `defaultCurrency()`) that read from `ShippingSetting` and apply fallbacks.
- **Usage**: `VolumetricWeightCalculator` and `ShippingQuoteService` receive `ShippingPricingConfigService` (injected) and use it for divisor, currency, fees, and rounding. No business numbers are hardcoded in the service layer.

## API and Admin

- **Quote preview**: `POST /api/shipping/quote-preview` (auth required). Body: `destination_country`, `weight`, `weight_unit` (kg/lb), `length`, `width`, `height`, `dimension_unit` (cm/in), optional `carrier`, `warehouse_mode`, `quantity`. Response includes normalized weights, currency, final amount, calculation notes, and applied config snapshot.
- **Admin**: **Config → Shipping (Calculation)** – single form to edit all keys above; PATCH persists to `shipping_settings` and clears cache.

## What the Next Task Should Implement

1. **Real carrier pricing tables** – Zone-based or rate-card data for DHL, UPS, FedEx (and any other carriers), instead of the current minimal formula (min charge + warehouse fee + optional markup/multi-package).
2. **Import flow integration** – When products are imported from external stores, use the normalized package input and `ShippingQuoteService::quote()` to estimate shipping (e.g. from product weight/dimensions and destination country/warehouse mode).
3. **Optional enhancements** – Apply rounding strategy to chargeable weight; apply carrier discount percentages to carrier-specific amounts; support multiple packages explicitly in the quote structure.

## File Layout

```
app/
  Models/
    ShippingSetting.php
  Services/Shipping/
    CarrierQuoteResult.php
    DimensionConverter.php
    NormalizedPackageInput.php
    PackageNormalizer.php
    ShippingPricingConfigService.php
    ShippingQuoteResult.php
    ShippingQuoteService.php
    VolumetricWeightCalculator.php
    WeightConverter.php
app/Http/Controllers/
  Admin/Config/
    ShippingSettingsController.php
  Api/
    ShippingQuoteController.php
database/
  migrations/
    2026_03_14_222501_create_shipping_settings_table.php
  seeders/
    ShippingSettingsSeeder.php
docs/
  SHIPPING_ENGINE_FOUNDATION.md
routes/
  admin.php   (shipping-settings edit/update)
  api.php     (shipping/quote-preview)
tests/
  Unit/Shipping/
    DimensionConverterTest.php
    PackageNormalizerTest.php
    ShippingPricingConfigServiceTest.php
    VolumetricWeightCalculatorTest.php
    WeightConverterTest.php
  Feature/
    ShippingQuotePreviewTest.php
```

Tests cover: kg/lb and cm/in conversion, volumetric and chargeable weight, normalized package input, config reads and fallbacks, and the quote-preview endpoint with auth and validation.
