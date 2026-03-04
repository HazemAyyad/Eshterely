<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class WarehousesController extends Controller
{
    public function index(): View
    {
        return view('admin.config.warehouses.index');
    }

    public function data(): JsonResponse
    {
        $query = Warehouse::query()->orderBy('label');

        return DataTables::eloquent($query)
            ->editColumn('country_code', fn (Warehouse $w) => $w->country_code ?? '-')
            ->editColumn('is_active', fn (Warehouse $w) => $w->is_active ? __('admin.yes') : __('admin.no'))
            ->addColumn('actions', function (Warehouse $w) {
                $editUrl = route('admin.config.warehouses.edit', $w);
                $destroyUrl = route('admin.config.warehouses.destroy', $w);
                return '<div class="d-flex align-items-center gap-1">' .
                    '<a href="' . $editUrl . '" class="btn btn-text-secondary rounded-pill waves-effect btn-icon" title="' . __('admin.edit') . '">' .
                        '<i class="icon-base ti tabler-edit icon-22px"></i></a> ' .
                    '<button type="button" class="btn btn-text-danger rounded-pill waves-effect btn-icon btn-delete" data-url="' . $destroyUrl . '" title="' . __('admin.delete') . '">' .
                        '<i class="icon-base ti tabler-trash icon-22px"></i></button>' .
                    '</div>';
            })
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function create(): View
    {
        return view('admin.config.warehouses.form', ['warehouse' => new Warehouse()]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:50|unique:warehouses,slug',
            'label' => 'required|string|max:100',
            'country_code' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        Warehouse::create($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.warehouse_added')]);
        }

        return redirect()->route('admin.config.warehouses.index')->with('success', __('admin.warehouse_added'));
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('admin.config.warehouses.form', ['warehouse' => $warehouse]);
    }

    public function update(Request $request, Warehouse $warehouse): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:50|unique:warehouses,slug,' . $warehouse->id,
            'label' => 'required|string|max:100',
            'country_code' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $warehouse->update($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.warehouse_updated')]);
        }

        return redirect()->route('admin.config.warehouses.index')->with('success', __('admin.warehouse_updated'));
    }

    public function destroy(Request $request, Warehouse $warehouse): JsonResponse|RedirectResponse
    {
        $warehouse->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.warehouse_deleted')]);
        }

        return redirect()->route('admin.config.warehouses.index')->with('success', __('admin.warehouse_deleted'));
    }
}
