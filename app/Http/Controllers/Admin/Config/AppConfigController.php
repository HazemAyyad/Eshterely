<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        ]);

        $apiBaseUrl = $request->input('api_base_url') ?: null;
        $developmentMode = (bool) $request->input('development_mode');

        if (!Schema::hasTable('app_config')) {
            return redirect()->route('admin.config.app-config')->with('error', __('admin.error'));
        }

        $row = DB::table('app_config')->first();
        $now = now();
        if ($row) {
            DB::table('app_config')->where('id', $row->id)->update([
                'api_base_url' => $apiBaseUrl,
                'development_mode' => $developmentMode,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('app_config')->insert([
                'api_base_url' => $apiBaseUrl,
                'development_mode' => $developmentMode,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
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

        return [
            'api_base_url' => $row->api_base_url ?? '',
            'development_mode' => (bool) ($row->development_mode ?? false),
        ];
    }
}
