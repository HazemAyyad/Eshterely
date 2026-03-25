<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\ProductImportLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductImportLogsController extends Controller
{
    public function index(): View
    {
        return view('admin.config.product-import.logs.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = ProductImportLog::query()->orderByDesc('created_at');

        if ($request->filled('store_key')) {
            $query->where('store_key', $request->input('store_key'));
        }
        if ($request->filled('success')) {
            $query->where('success', (bool) $request->input('success'));
        }

        $logs = $query->paginate(50);

        return response()->json($logs);
    }
}
