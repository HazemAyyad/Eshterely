<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\CustomerCodeSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerCodeSettingsController extends Controller
{
    public function edit(): View
    {
        $row = CustomerCodeSetting::current();

        return view('admin.config.customer-code-settings', [
            'prefix' => $row->prefix,
            'numeric_padding' => $row->numeric_padding,
        ]);
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'prefix' => 'required|string|max:16|regex:/^[A-Za-z][A-Za-z0-9]*$/',
            'numeric_padding' => 'required|integer|min:1|max:12',
        ]);

        $row = CustomerCodeSetting::current();
        $row->prefix = strtoupper($validated['prefix']);
        $row->numeric_padding = (int) $validated['numeric_padding'];
        $row->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }

        return redirect()->route('admin.config.customer-code-settings.edit')->with('success', __('admin.success'));
    }
}
