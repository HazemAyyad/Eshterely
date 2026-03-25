<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\ProductImportStoreSetting;
use App\Services\ProductImport\StoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductImportStoreSettingsController extends Controller
{
    public function index(): View
    {
        $settings = ProductImportStoreSetting::query()
            ->orderBy('store_key')
            ->get();

        // Ensure all known stores have a row (create defaults on the fly if missing).
        $existingKeys = $settings->pluck('store_key')->all();
        foreach (StoreResolver::knownStores() as $key) {
            if (! in_array($key, $existingKeys, true)) {
                ProductImportStoreSetting::query()->create([
                    'store_key'   => $key,
                    'display_name' => ucfirst($key),
                    'is_enabled'  => true,
                ]);
            }
        }

        $settings = ProductImportStoreSetting::query()->orderBy('store_key')->get();

        return view('admin.config.product-import.store-settings.index', compact('settings'));
    }

    public function edit(ProductImportStoreSetting $setting): View
    {
        return view('admin.config.product-import.store-settings.edit', compact('setting'));
    }

    public function update(Request $request, ProductImportStoreSetting $setting): RedirectResponse
    {
        $validated = $request->validate([
            'display_name'                          => 'nullable|string|max:100',
            'is_enabled'                            => 'boolean',
            'free_attempts_enabled'                 => 'boolean',
            'allow_ai_extraction'                   => 'boolean',
            'allow_playwright_fallback'             => 'boolean',
            'playwright_priority'                   => 'integer|min:1|max:10',
            'playwright_timeout_seconds'            => 'integer|min:5|max:120',
            'allow_paid_fallback'                   => 'boolean',
            'paid_provider'                         => 'nullable|string|max:50',
            'paid_provider_priority'                => 'integer|min:1|max:10',
            'attempt_order'                         => 'nullable|string',
            'max_retries'                           => 'integer|min:1|max:5',
            'timeout_seconds'                       => 'integer|min:5|max:120',
            'minimum_confidence'                    => 'numeric|min:0|max:1',
            'requires_manual_review_for_missing_specs' => 'boolean',
        ]);

        // Convert checkboxes (absent = false)
        foreach (['is_enabled', 'free_attempts_enabled', 'allow_ai_extraction', 'allow_playwright_fallback', 'allow_paid_fallback', 'requires_manual_review_for_missing_specs'] as $bool) {
            $validated[$bool] = (bool) ($request->has($bool) ? $request->input($bool, 0) : 0);
        }

        // attempt_order: comma-separated string → array
        if (isset($validated['attempt_order']) && is_string($validated['attempt_order'])) {
            $validated['attempt_order'] = array_values(
                array_filter(array_map('trim', explode(',', $validated['attempt_order'])))
            );
        }

        $setting->update($validated);

        return redirect()
            ->route('admin.config.product-import.store-settings.index')
            ->with('success', 'Settings updated for ' . $setting->store_key);
    }
}
