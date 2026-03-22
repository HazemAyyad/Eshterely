<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\FeaturedStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class FeaturedStoresController extends Controller
{
    public function index(): View
    {
        return view('admin.config.featured-stores.index');
    }

    public function data(): JsonResponse
    {
        $query = FeaturedStore::query()
            ->orderBy('is_active', 'desc')
            ->orderBy('is_featured', 'desc')
            ->orderBy('name');

        return DataTables::eloquent($query)
            ->editColumn('country_code', fn (FeaturedStore $s) => $s->country_code ?? '-')
            ->editColumn('is_active', fn (FeaturedStore $s) => $s->is_active ? __('admin.yes') : __('admin.no'))
            ->editColumn('is_featured', fn (FeaturedStore $s) => $s->is_featured ? __('admin.yes') : __('admin.no'))
            ->addColumn('actions', function (FeaturedStore $s) {
                $editUrl = route('admin.config.featured-stores.edit', $s);
                $destroyUrl = route('admin.config.featured-stores.destroy', $s);
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
        return view('admin.config.featured-stores.form', ['store' => new FeaturedStore()]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'store_slug' => 'required|string|max:50|unique:featured_stores,store_slug',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'categories' => 'nullable|string',
            'logo' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'country_code' => 'nullable|string|max:10',
            'store_url' => 'nullable|string|max:500',
            'is_featured' => 'boolean',
        ]);
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $request->has('is_active');
        unset($validated['logo']);

        $store = FeaturedStore::create($validated);
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('config/featured-stores', 'public');
            $store->update(['logo_url' => $path]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.store_added')]);
        }

        return redirect()->route('admin.config.featured-stores.index')->with('success', __('admin.store_added'));
    }

    public function edit(FeaturedStore $featured_store): View
    {
        return view('admin.config.featured-stores.form', ['store' => $featured_store]);
    }

    public function update(Request $request, FeaturedStore $featured_store): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'store_slug' => 'required|string|max:50|unique:featured_stores,store_slug,' . $featured_store->id,
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'categories' => 'nullable|string',
            'logo' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'country_code' => 'nullable|string|max:10',
            'store_url' => 'nullable|string|max:500',
            'is_featured' => 'boolean',
        ]);
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $request->has('is_active');
        unset($validated['logo']);

        $featured_store->update($validated);
        if ($request->hasFile('logo')) {
            if ($featured_store->logo_url && !str_starts_with($featured_store->logo_url, 'http')) {
                Storage::disk('public')->delete($featured_store->logo_url);
            }
            $path = $request->file('logo')->store('config/featured-stores', 'public');
            $featured_store->update(['logo_url' => $path]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.store_updated')]);
        }

        return redirect()->route('admin.config.featured-stores.index')->with('success', __('admin.store_updated'));
    }

    public function destroy(Request $request, FeaturedStore $featured_store): JsonResponse|RedirectResponse
    {
        $featured_store->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.store_deleted')]);
        }

        return redirect()->route('admin.config.featured-stores.index')->with('success', __('admin.store_deleted'));
    }
}
