<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\PromoBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class PromoBannersController extends Controller
{
    public function index(): View
    {
        return view('admin.config.promo-banners.index');
    }

    public function data(): JsonResponse
    {
        $query = PromoBanner::query()->orderBy('sort_order');

        return DataTables::eloquent($query)
            ->editColumn('label', fn (PromoBanner $b) => \Str::limit($b->label ?? '-', 20))
            ->editColumn('title', fn (PromoBanner $b) => \Str::limit($b->title ?? '-', 30))
            ->editColumn('is_active', fn (PromoBanner $b) => $b->is_active ? __('admin.yes') : __('admin.no'))
            ->editColumn('start_at', fn (PromoBanner $b) => $b->start_at?->format('Y-m-d') ?? '-')
            ->editColumn('end_at', fn (PromoBanner $b) => $b->end_at?->format('Y-m-d') ?? '-')
            ->addColumn('actions', function (PromoBanner $b) {
                $editUrl = route('admin.config.promo-banners.edit', $b);
                $destroyUrl = route('admin.config.promo-banners.destroy', $b);
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
        return view('admin.config.promo-banners.form', ['banner' => new PromoBanner()]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'label' => 'nullable|string|max:100',
            'title' => 'nullable|string|max:200',
            'cta_text' => 'nullable|string|max:100',
            'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'deep_link' => 'nullable|string|max:500',
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
        ]);
        $validated['is_active'] = $request->boolean('is_active');
        unset($validated['image']);

        $banner = PromoBanner::create($validated);
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('config/promo-banners', 'public');
            $banner->update(['image_url' => $path]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.banner_added')]);
        }

        return redirect()->route('admin.config.promo-banners.index')->with('success', __('admin.banner_added'));
    }

    public function edit(PromoBanner $promo_banner): View
    {
        return view('admin.config.promo-banners.form', ['banner' => $promo_banner]);
    }

    public function update(Request $request, PromoBanner $promo_banner): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'label' => 'nullable|string|max:100',
            'title' => 'nullable|string|max:200',
            'cta_text' => 'nullable|string|max:100',
            'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'deep_link' => 'nullable|string|max:500',
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date',
        ]);
        $validated['is_active'] = $request->boolean('is_active');
        unset($validated['image']);

        $promo_banner->update($validated);
        if ($request->hasFile('image')) {
            if ($promo_banner->image_url && !str_starts_with($promo_banner->image_url, 'http')) {
                Storage::disk('public')->delete($promo_banner->image_url);
            }
            $path = $request->file('image')->store('config/promo-banners', 'public');
            $promo_banner->update(['image_url' => $path]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.banner_updated')]);
        }

        return redirect()->route('admin.config.promo-banners.index')->with('success', __('admin.banner_updated'));
    }

    public function destroy(Request $request, PromoBanner $promo_banner): JsonResponse|RedirectResponse
    {
        $promo_banner->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.banner_deleted')]);
        }

        return redirect()->route('admin.config.promo-banners.index')->with('success', __('admin.banner_deleted'));
    }
}
