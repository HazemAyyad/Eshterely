<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $s = DB::table('user_settings')->where('user_id', $userId)->first();

        $languageCode = $this->normalizeLanguageCode($s->language_code ?? null);
        $currencyCode = $this->normalizeCurrencyCode($s->currency_code ?? null);
        $warehouseId = $this->normalizeWarehouseId($s->default_warehouse_id ?? null);

        $warehouseLabel = $s->default_warehouse_label ?? null;
        if (empty($warehouseLabel) && !empty($warehouseId)) {
            $warehouseLabel = DB::table('warehouses')->where('slug', $warehouseId)->value('label');
        }

        return response()->json([
            'language_code' => $languageCode,
            'language_label' => $this->languageLabel($languageCode),
            'currency_code' => $currencyCode,
            'currency_symbol' => $this->currencySymbol($currencyCode),
            'default_warehouse_id' => $warehouseId,
            'default_warehouse_label' => $warehouseLabel ?? '',
            'smart_consolidation_enabled' => (bool) ($s->smart_consolidation_enabled ?? true),
            'auto_insurance_enabled' => (bool) ($s->auto_insurance_enabled ?? false),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language_code' => 'sometimes|string|max:10',
            'currency_code' => 'sometimes|string|max:10',
            'default_warehouse_id' => 'sometimes|nullable|string|max:100',
            'default_warehouse_label' => 'sometimes|nullable|string|max:255',
            'smart_consolidation_enabled' => 'sometimes|boolean',
            'auto_insurance_enabled' => 'sometimes|boolean',
        ]);

        $userId = $request->user()->id;

        $now = now();
        $existing = DB::table('user_settings')->where('user_id', $userId)->first();

        $update = [];
        if (array_key_exists('language_code', $validated)) {
            $update['language_code'] = $this->normalizeLanguageCode($validated['language_code']);
        }
        if (array_key_exists('currency_code', $validated)) {
            $update['currency_code'] = $this->normalizeCurrencyCode($validated['currency_code']);
        }
        if (array_key_exists('default_warehouse_id', $validated)) {
            $update['default_warehouse_id'] = $this->normalizeWarehouseId($validated['default_warehouse_id']);
        }
        if (array_key_exists('default_warehouse_label', $validated)) {
            $update['default_warehouse_label'] = $validated['default_warehouse_label'];
        }
        if (array_key_exists('smart_consolidation_enabled', $validated)) {
            $update['smart_consolidation_enabled'] = (bool) $validated['smart_consolidation_enabled'];
        }
        if (array_key_exists('auto_insurance_enabled', $validated)) {
            $update['auto_insurance_enabled'] = (bool) $validated['auto_insurance_enabled'];
        }

        $update['updated_at'] = $now;
        if (!$existing) {
            $update['created_at'] = $now;
        }

        DB::table('user_settings')->updateOrInsert(['user_id' => $userId], $update);

        return $this->show($request);
    }

    private function normalizeLanguageCode(?string $code): string
    {
        $c = Str::lower(trim((string) $code));
        return in_array($c, ['en', 'ar'], true) ? $c : 'en';
    }

    private function languageLabel(string $code): string
    {
        return $code === 'ar' ? 'العربية' : 'English';
    }

    private function normalizeCurrencyCode(?string $code): string
    {
        $c = Str::upper(trim((string) $code));
        if ($c === '') {
            return 'USD';
        }
        // Keep backward compatibility: accept unknown but normalize casing.
        return $c;
    }

    private function currencySymbol(string $currencyCode): string
    {
        return match ($currencyCode) {
            'USD' => '$',
            'AED' => 'د.إ',
            'SAR' => '﷼',
            'EUR' => '€',
            'GBP' => '£',
            default => '',
        };
    }

    private function normalizeWarehouseId(?string $warehouseId): ?string
    {
        $w = trim((string) $warehouseId);
        if ($w === '') {
            return null;
        }
        return $w;
    }
}
