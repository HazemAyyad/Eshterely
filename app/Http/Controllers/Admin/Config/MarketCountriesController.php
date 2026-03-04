<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\MarketCountry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class MarketCountriesController extends Controller
{
    public function index(): View
    {
        return view('admin.config.market-countries.index');
    }

    public function data(): JsonResponse
    {
        $query = MarketCountry::query()->orderBy('is_featured', 'desc')->orderBy('name');

        return DataTables::eloquent($query)
            ->editColumn('flag_emoji', fn (MarketCountry $c) => $c->flag_emoji ?? '-')
            ->editColumn('is_featured', fn (MarketCountry $c) => $c->is_featured ? __('admin.yes') : __('admin.no'))
            ->addColumn('actions', function (MarketCountry $c) {
                $editUrl = route('admin.config.market-countries.edit', $c);
                $destroyUrl = route('admin.config.market-countries.destroy', $c);
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
        return view('admin.config.market-countries.form', ['country' => new MarketCountry()]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10',
            'name' => 'required|string|max:100',
            'flag_emoji' => 'nullable|string|max:10',
            'is_featured' => 'boolean',
        ]);
        $validated['is_featured'] = $request->boolean('is_featured');

        MarketCountry::create($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.country_added')]);
        }

        return redirect()->route('admin.config.market-countries.index')->with('success', __('admin.country_added'));
    }

    public function edit(MarketCountry $market_country): View
    {
        return view('admin.config.market-countries.form', ['country' => $market_country]);
    }

    public function update(Request $request, MarketCountry $market_country): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10',
            'name' => 'required|string|max:100',
            'flag_emoji' => 'nullable|string|max:10',
            'is_featured' => 'boolean',
        ]);
        $validated['is_featured'] = $request->boolean('is_featured');

        $market_country->update($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.country_updated')]);
        }

        return redirect()->route('admin.config.market-countries.index')->with('success', __('admin.country_updated'));
    }

    public function destroy(Request $request, MarketCountry $market_country): JsonResponse|RedirectResponse
    {
        $market_country->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.country_deleted')]);
        }

        return redirect()->route('admin.config.market-countries.index')->with('success', __('admin.country_deleted'));
    }
}
