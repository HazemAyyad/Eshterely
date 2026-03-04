<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\OnboardingPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class OnboardingController extends Controller
{
    public function index(): View
    {
        return view('admin.config.onboarding.index');
    }

    public function data(): JsonResponse
    {
        $query = OnboardingPage::query()->orderBy('sort_order');

        return DataTables::eloquent($query)
            ->editColumn('title_en', fn (OnboardingPage $page) => \Str::limit($page->title_en, 40))
            ->editColumn('title_ar', fn (OnboardingPage $page) => \Str::limit($page->title_ar ?? '-', 40))
            ->addColumn('actions', function (OnboardingPage $page) {
                $editUrl = route('admin.config.onboarding.edit', $page);
                $destroyUrl = route('admin.config.onboarding.destroy', $page);
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
        return view('admin.config.onboarding.form', ['page' => new OnboardingPage()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sort_order' => 'required|integer|min:0',
            'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'title_en' => 'required|string|max:200',
            'title_ar' => 'nullable|string|max:200',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
        ]);
        unset($validated['image']);

        $page = OnboardingPage::create($validated);
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('config/onboarding', 'public');
            $page->update(['image_url' => $path]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.page_added')]);
        }

        return redirect()->route('admin.config.onboarding.index')->with('success', __('admin.page_added'));
    }

    public function edit(OnboardingPage $onboarding): View
    {
        return view('admin.config.onboarding.form', ['page' => $onboarding]);
    }

    public function update(Request $request, OnboardingPage $onboarding): RedirectResponse
    {
        $validated = $request->validate([
            'sort_order' => 'required|integer|min:0',
            'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'title_en' => 'required|string|max:200',
            'title_ar' => 'nullable|string|max:200',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
        ]);
        unset($validated['image']);

        $onboarding->update($validated);
        if ($request->hasFile('image')) {
            if ($onboarding->image_url && !str_starts_with($onboarding->image_url, 'http')) {
                Storage::disk('public')->delete($onboarding->image_url);
            }
            $path = $request->file('image')->store('config/onboarding', 'public');
            $onboarding->update(['image_url' => $path]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.page_updated')]);
        }

        return redirect()->route('admin.config.onboarding.index')->with('success', __('admin.page_updated'));
    }

    public function destroy(Request $request, OnboardingPage $onboarding): JsonResponse|RedirectResponse
    {
        $onboarding->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.page_deleted')]);
        }

        return redirect()->route('admin.config.onboarding.index')->with('success', __('admin.page_deleted'));
    }
}
