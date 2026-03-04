<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\SplashConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SplashConfigController extends Controller
{
    public function edit(): View
    {
        $splash = SplashConfig::first() ?? new SplashConfig();

        return view('admin.config.splash', compact('splash'));
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'logo' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'title_en' => 'nullable|string|max:100',
            'title_ar' => 'nullable|string|max:100',
            'subtitle_en' => 'nullable|string|max:200',
            'subtitle_ar' => 'nullable|string|max:200',
            'progress_text_en' => 'nullable|string|max:100',
            'progress_text_ar' => 'nullable|string|max:100',
        ]);
        unset($validated['logo']);

        $splash = SplashConfig::first();
        if (!$splash) {
            $splash = SplashConfig::create($validated);
        } else {
            $splash->update($validated);
        }

        if ($request->hasFile('logo')) {
            if ($splash->logo_url && !str_starts_with($splash->logo_url, 'http')) {
                Storage::disk('public')->delete($splash->logo_url);
            }
            $path = $request->file('logo')->store('config/splash', 'public');
            $splash->update(['logo_url' => $path]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }
        return redirect()->route('admin.config.splash')->with('success', __('admin.success'));
    }
}
