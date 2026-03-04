<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\ThemeConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ThemeConfigController extends Controller
{
    public function edit(): View
    {
        $theme = ThemeConfig::first() ?? new ThemeConfig([
            'primary_color' => '1E66F5',
            'background_color' => 'FFFFFF',
            'text_color' => '0B1220',
            'muted_text_color' => '6B7280',
        ]);

        return view('admin.config.theme', compact('theme'));
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'primary_color' => 'required|string|max:10',
            'background_color' => 'required|string|max:10',
            'text_color' => 'required|string|max:10',
            'muted_text_color' => 'required|string|max:10',
        ]);

        $theme = ThemeConfig::first();
        if ($theme) {
            $theme->update($validated);
        } else {
            ThemeConfig::create($validated);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }
        return redirect()->route('admin.config.theme')->with('success', __('admin.success'));
    }
}
