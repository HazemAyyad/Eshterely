<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $s = DB::table('user_settings')->where('user_id', $request->user()->id)->first();

        return response()->json([
            'language_code' => $s->language_code ?? 'en',
            'language_label' => $s->language_code === 'ar' ? 'العربية' : 'English (US)',
            'currency_code' => $s->currency_code ?? 'USD',
            'currency_symbol' => '$',
            'default_warehouse_id' => $s->default_warehouse_id ?? 'delaware_us',
            'default_warehouse_label' => $s->default_warehouse_label ?? 'Delaware, US',
            'smart_consolidation_enabled' => (bool) ($s->smart_consolidation_enabled ?? true),
            'auto_insurance_enabled' => (bool) ($s->auto_insurance_enabled ?? false),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language_code' => 'string|max:10',
            'currency_code' => 'string|max:10',
            'default_warehouse_id' => 'nullable|string',
            'default_warehouse_label' => 'nullable|string',
            'smart_consolidation_enabled' => 'boolean',
            'auto_insurance_enabled' => 'boolean',
        ]);

        $userId = $request->user()->id;
        DB::table('user_settings')->updateOrInsert(
            ['user_id' => $userId],
            array_merge($validated, ['updated_at' => now(), 'created_at' => now()])
        );

        return response()->json(['message' => 'Updated']);
    }
}
