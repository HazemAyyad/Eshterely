<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AppConfigController extends Controller
{
    public function edit(): View
    {
        $config = $this->getConfig();

        return view('admin.config.app-config', compact('config'));
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'api_base_url' => 'nullable|url|max:500',
            'development_mode' => 'nullable|boolean',
            'app_name' => 'nullable|string|max:100',
            'app_icon' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $apiBaseUrl = $request->input('api_base_url') ?: null;
        $developmentMode = (bool) $request->input('development_mode');
        $appName = $request->input('app_name') ?: null;

        if (!Schema::hasTable('app_config')) {
            return redirect()->route('admin.config.app-config')->with('error', __('admin.error'));
        }

        $row = DB::table('app_config')->first();
        $now = now();
        $data = [
            'api_base_url' => $apiBaseUrl,
            'development_mode' => $developmentMode,
            'updated_at' => $now,
        ];
        if (Schema::hasColumn('app_config', 'app_name')) {
            $data['app_name'] = $appName;
        }

        $appIconPath = null;
        if (Schema::hasColumn('app_config', 'app_icon_url')) {
            $appIconPath = $row ? ($row->app_icon_url ?? null) : null;
            if ($request->hasFile('app_icon')) {
                if ($appIconPath && !str_starts_with($appIconPath, 'http')) {
                    Storage::disk('public')->delete($appIconPath);
                }
                $appIconPath = $request->file('app_icon')->store('config/app-icon', 'public');
                $data['app_icon_url'] = $appIconPath;
            } elseif ($row && ($row->app_icon_url ?? '') !== '') {
                $data['app_icon_url'] = $row->app_icon_url;
            }
        }

        if ($row) {
            DB::table('app_config')->where('id', $row->id)->update($data);
        } else {
            $data['created_at'] = $now;
            if (Schema::hasColumn('app_config', 'app_icon_url') && !array_key_exists('app_icon_url', $data)) {
                $data['app_icon_url'] = null;
            }
            DB::table('app_config')->insert($data);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }

        return redirect()->route('admin.config.app-config')->with('success', __('admin.success'));
    }

    private function getConfig(): array
    {
        if (!Schema::hasTable('app_config')) {
            return ['api_base_url' => '', 'development_mode' => false];
        }

        $row = DB::table('app_config')->first();
        if (!$row) {
            return ['api_base_url' => '', 'development_mode' => false];
        }

        $config = [
            'api_base_url' => $row->api_base_url ?? '',
            'development_mode' => (bool) ($row->development_mode ?? false),
        ];
        if (Schema::hasColumn('app_config', 'app_name')) {
            $config['app_name'] = $row->app_name ?? '';
        }
        if (Schema::hasColumn('app_config', 'app_icon_url')) {
            $config['app_icon_url'] = $row->app_icon_url ?? '';
        }
        return $config;
    }
}
