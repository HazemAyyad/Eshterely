<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class PromoCodesController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_codes' => PromoCode::count(),
            'active_codes' => PromoCode::where('is_active', true)->count(),
            'total_redemptions' => PromoRedemption::where('status', 'applied')->count(),
            'total_discount' => PromoRedemption::where('status', 'applied')->sum('discount_amount'),
        ];

        $recentRedemptions = PromoRedemption::with(['promoCode', 'user', 'order'])
            ->orderByDesc('redeemed_at')
            ->limit(15)
            ->get();

        return view('admin.config.promo-codes.index', compact('stats', 'recentRedemptions'));
    }

    public function data(): JsonResponse
    {
        $query = PromoCode::query()->withCount(['redemptions as usages_count' => fn ($q) => $q->where('status', 'applied')]);

        return DataTables::eloquent($query)
            ->editColumn('code', fn (PromoCode $promo) => '<span class="fw-semibold">' . e($promo->code) . '</span>')
            ->editColumn('discount_type', fn (PromoCode $promo) => $this->discountLabel($promo))
            ->editColumn('discount_value', fn (PromoCode $promo) => $this->discountValueLabel($promo))
            ->editColumn('is_active', fn (PromoCode $promo) => $promo->is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>')
            ->editColumn('starts_at', fn (PromoCode $promo) => $promo->starts_at?->format('Y-m-d H:i') ?? '-')
            ->editColumn('ends_at', fn (PromoCode $promo) => $promo->ends_at?->format('Y-m-d H:i') ?? '-')
            ->addColumn('usages_count', fn (PromoCode $promo) => number_format((int) ($promo->usages_count ?? 0)))
            ->addColumn('limits', fn (PromoCode $promo) => $this->limitsLabel($promo))
            ->addColumn('actions', function (PromoCode $promo) {
                $editUrl = route('admin.config.promo-codes.edit', $promo);
                $toggleUrl = route('admin.config.promo-codes.destroy', $promo);
                $label = $promo->is_active ? 'Disable' : 'Delete';

                return '<div class="d-flex align-items-center gap-1">' .
                    '<a href="' . $editUrl . '" class="btn btn-text-secondary rounded-pill waves-effect btn-icon" title="' . __('admin.edit') . '">' .
                        '<i class="icon-base ti tabler-edit icon-22px"></i></a>' .
                    '<button type="button" class="btn btn-text-danger rounded-pill waves-effect btn-icon btn-delete" data-url="' . $toggleUrl . '" data-label="' . e($label) . '" title="' . e($label) . '">' .
                        '<i class="icon-base ti tabler-power icon-22px"></i></button>' .
                    '</div>';
            })
            ->rawColumns(['code', 'discount_type', 'discount_value', 'is_active', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        return view('admin.config.promo-codes.form', ['promo' => new PromoCode()]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $this->validatePromoCode($request);
        $validated['code'] = Str::upper(trim($validated['code']));
        $validated['is_active'] = $request->boolean('is_active');

        PromoCode::create($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.promo_code_added')]);
        }

        return redirect()->route('admin.config.promo-codes.index')->with('success', __('admin.promo_code_added'));
    }

    public function edit(PromoCode $promo_code): View
    {
        return view('admin.config.promo-codes.form', ['promo' => $promo_code]);
    }

    public function update(Request $request, PromoCode $promo_code): JsonResponse|RedirectResponse
    {
        $validated = $this->validatePromoCode($request, $promo_code);
        $validated['code'] = Str::upper(trim($validated['code']));
        $validated['is_active'] = $request->boolean('is_active');

        $promo_code->update($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.promo_code_updated')]);
        }

        return redirect()->route('admin.config.promo-codes.index')->with('success', __('admin.promo_code_updated'));
    }

    public function destroy(Request $request, PromoCode $promo_code): JsonResponse|RedirectResponse
    {
        $promo_code->update(['is_active' => false]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.promo_code_disabled')]);
        }

        return redirect()->route('admin.config.promo-codes.index')->with('success', __('admin.promo_code_disabled'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePromoCode(Request $request, ?PromoCode $promo = null): array
    {
        return $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes,code' . ($promo ? ',' . $promo->id : ''),
            'description' => 'nullable|string|max:2000',
            'discount_type' => 'required|string|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'max_usage_total' => 'nullable|integer|min:1',
            'max_usage_per_user' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
        ]);
    }

    private function discountLabel(PromoCode $promo): string
    {
        return match (strtolower((string) $promo->discount_type)) {
            'percent', 'percentage' => 'Percent',
            default => 'Fixed',
        };
    }

    private function discountValueLabel(PromoCode $promo): string
    {
        return match (strtolower((string) $promo->discount_type)) {
            'percent', 'percentage' => rtrim(rtrim(number_format((float) $promo->discount_value, 2), '0'), '.') . '%',
            default => '$' . number_format((float) $promo->discount_value, 2),
        };
    }

    private function limitsLabel(PromoCode $promo): string
    {
        $parts = [];
        if ($promo->min_order_amount !== null) {
            $parts[] = 'min $' . number_format((float) $promo->min_order_amount, 2);
        }
        if ($promo->max_discount_amount !== null) {
            $parts[] = 'cap $' . number_format((float) $promo->max_discount_amount, 2);
        }
        if ($promo->max_usage_total !== null) {
            $parts[] = 'global ' . (int) $promo->max_usage_total;
        }
        if ($promo->max_usage_per_user !== null) {
            $parts[] = 'per user ' . (int) $promo->max_usage_per_user;
        }

        return $parts === [] ? '-' : implode(' | ', $parts);
    }
}
