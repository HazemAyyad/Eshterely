<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Notification;
use App\Services\Fcm\NotificationDispatchService;
use App\Services\Shipping\CartShippingEstimateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CartReviewController extends Controller
{
    public function __construct(
        protected NotificationDispatchService $dispatchService,
        protected CartShippingEstimateService $cartShippingEstimate,
    ) {}

    public function index(): View
    {
        return view('admin.cart-review.index');
    }

    public function data(): JsonResponse
    {
        $query = CartItem::with('user')
            ->orderBy('created_at', 'desc');

        return DataTables::eloquent($query)
            ->addColumn('image', fn (CartItem $item) => $this->renderImageThumb($item))
            ->editColumn('name', fn (CartItem $item) => \Str::limit($item->name, 40))
            ->editColumn('store_name', fn (CartItem $item) => $item->store_name ?? '-')
            ->addColumn('user_name', fn (CartItem $item) => $item->user?->full_name ?? $item->user?->name ?? '-')
            ->addColumn('user_contact', fn (CartItem $item) => $item->user?->phone ?? $item->user?->email ?? '-')
            ->editColumn('unit_price', fn (CartItem $item) => number_format((float) $item->unit_price, 2) . ' ' . $item->currency)
            ->addColumn('variation_text', fn (CartItem $item) => $item->variation_text ? \Str::limit($item->variation_text, 30) : '-')
            ->addColumn('weight_dims', fn (CartItem $item) => $this->formatPackageColumn($item))
            ->addColumn('shipping_destination', fn (CartItem $item) => $this->formatShippingDestination($item))
            ->addColumn('shipping_basis', fn (CartItem $item) => $this->formatShippingBasis($item))
            ->addColumn('shipping_cost_edit', function (CartItem $item) {
                $url = route('admin.cart-review.shipping', $item->id);
                $recalcUrl = route('admin.cart-review.recalculate-shipping', $item->id);
                $val = $item->shipping_cost !== null ? number_format((float) $item->shipping_cost, 2, '.', '') : '';
                $recalcLabel = e(__('admin.recalculate_shipping'));
                $saveLabel = e(__('admin.save'));
                $hint = e(__('admin.shipping_manual_hint'));

                return '<div class="d-flex flex-column gap-2 cart-review-shipping-col" style="min-width:11rem">'
                    . '<button type="button" class="btn btn-sm btn-primary btn-recalc-shipping" data-url="' . e($recalcUrl) . '">'
                    . '<i class="icon-base ti tabler-refresh me-1"></i>' . $recalcLabel . '</button>'
                    . '<div class="input-group input-group-sm">'
                    . '<input type="number" step="0.01" min="0" class="form-control shipping-cost-input" data-id="' . $item->id . '" data-url="' . e($url) . '" value="' . e($val) . '" placeholder="0.00" title="' . e(__('admin.shipping_cost')) . '">'
                    . '<button type="button" class="btn btn-outline-primary btn-save-shipping" data-id="' . $item->id . '" data-url="' . e($url) . '">' . $saveLabel . '</button>'
                    . '</div>'
                    . '<small class="text-muted">' . $hint . '</small>'
                    . '</div>';
            })
            ->editColumn('created_at', fn (CartItem $item) => $item->created_at?->format('Y-m-d H:i'))
            ->addColumn('actions', function (CartItem $item) {
                $approveUrl = route('admin.cart-review.update', $item->id);
                $reviewBtns = $item->review_status === 'pending_review'
                    ? '<button type="button" class="btn btn-sm btn-success btn-approve" data-url="' . e($approveUrl) . '" data-status="reviewed">' . __('admin.approve') . '</button> ' .
                      '<button type="button" class="btn btn-sm btn-danger btn-reject" data-url="' . e($approveUrl) . '" data-status="rejected">' . __('admin.reject') . '</button> '
                    : '<span class="badge bg-' . ($item->review_status === 'reviewed' ? 'success' : 'secondary') . '">' . e($item->review_status) . '</span>';
                return '<button type="button" class="btn btn-sm btn-info btn-details me-1" data-details="' . e(json_encode($this->itemDetailsForModal($item))) . '">' . __('admin.details') . '</button> ' .
                    $reviewBtns;
            })
            ->rawColumns(['image', 'weight_dims', 'shipping_destination', 'shipping_basis', 'actions', 'shipping_cost_edit'])
            ->toJson();
    }

    /**
     * Weight & dimensions with labels + edit button (modal saves via updatePackage).
     */
    private function formatPackageColumn(CartItem $item): string
    {
        $hasWt = $item->weight !== null && (float) $item->weight > 0;
        $weightLine = $hasWt
            ? '<strong>' . e((string) (float) $item->weight) . '</strong> ' . e((string) ($item->weight_unit ?? ''))
            : '<span class="text-muted">—</span>';

        $hasDim = $item->length !== null || $item->width !== null || $item->height !== null;
        $dimsLine = $hasDim
            ? '<strong>' . e((string) ($item->length ?? '—')) . '</strong>×<strong>' . e((string) ($item->width ?? '—')) . '</strong>×<strong>' . e((string) ($item->height ?? '—')) . '</strong>'
                . ' <span class="text-muted">' . e((string) ($item->dimension_unit ?? '')) . '</span>'
            : '<span class="text-muted">—</span>';

        $pkg = [
            'id' => $item->id,
            'weight' => $hasWt ? (float) $item->weight : null,
            'weight_unit' => $item->weight_unit,
            'length' => $item->length !== null ? (float) $item->length : null,
            'width' => $item->width !== null ? (float) $item->width : null,
            'height' => $item->height !== null ? (float) $item->height : null,
            'dimension_unit' => $item->dimension_unit,
        ];
        $pkgJson = e(json_encode($pkg, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
        $savePkgUrl = e(route('admin.cart-review.package', $item->id));

        $wtLabel = e(__('admin.package_weight_label'));
        $dimLabel = e(__('admin.package_dims_label'));

        $snap = is_array($item->shipping_snapshot) ? $item->shipping_snapshot : [];
        $mSrc = isset($snap['measurements_source']) ? (string) $snap['measurements_source'] : null;
        $srcBadge = '';
        if ($mSrc === 'exact') {
            $srcBadge = '<span class="badge bg-label-success ms-1">' . e(__('admin.measurements_imported_exact')) . '</span>';
        } elseif ($mSrc === 'fallback') {
            $srcBadge = '<span class="badge bg-label-warning ms-1">' . e(__('admin.measurements_fallback_defaults')) . '</span>';
        } elseif ((bool) $item->estimated) {
            $srcBadge = '<span class="badge bg-label-warning ms-1">' . e(__('admin.measurements_fallback_defaults')) . '</span>';
        }

        return '<div class="small cart-review-package-col" style="min-width:10rem">'
            . '<div class="mb-1">' . $wtLabel . ' ' . $weightLine . $srcBadge . '</div>'
            . '<div class="mb-2">' . $dimLabel . ' ' . $dimsLine . '</div>'
            . '<button type="button" class="btn btn-sm btn-label-secondary btn-edit-package" data-save-url="' . $savePkgUrl . '" data-package="' . $pkgJson . '">'
            . '<i class="icon-base ti tabler-edit me-1"></i>' . e(__('admin.edit_package')) . '</button>'
            . '</div>';
    }

    private function itemDetailsForModal(CartItem $item): array
    {
        $snap = is_array($item->shipping_snapshot) ? $item->shipping_snapshot : [];

        return [
            'id' => $item->id,
            'name' => $item->name,
            'image_url' => $item->image_url,
            'product_url' => $item->product_url,
            'unit_price' => $item->unit_price,
            'quantity' => $item->quantity,
            'currency' => $item->currency,
            'store_name' => $item->store_name,
            'store_key' => $item->store_key,
            'product_id' => $item->product_id,
            'country' => $item->country,
            'variation_text' => $item->variation_text,
            'weight' => $item->weight,
            'weight_unit' => $item->weight_unit,
            'length' => $item->length,
            'width' => $item->width,
            'height' => $item->height,
            'dimension_unit' => $item->dimension_unit,
            'source' => $item->source,
            'review_status' => $item->review_status,
            'shipping_cost' => $item->shipping_cost,
            'shipping_basis' => strip_tags($this->formatShippingBasis($item)),
            'shipping_destination' => strip_tags($this->formatShippingDestination($item)),
            'measurements_source' => $snap['measurements_source'] ?? null,
            'missing_fields' => $item->missing_fields ?? [],
            'estimated_flag' => (bool) $item->estimated,
            'shipping_snapshot_excerpt' => [
                'amount' => $snap['amount'] ?? null,
                'currency' => $snap['currency'] ?? null,
                'chargeable_weight' => $snap['chargeable_weight'] ?? null,
                'measurements_source' => $snap['measurements_source'] ?? null,
                'package_weight' => $snap['package_weight'] ?? null,
                'package_length' => $snap['package_length'] ?? null,
                'package_width' => $snap['package_width'] ?? null,
                'package_height' => $snap['package_height'] ?? null,
            ],
            'created_at' => $item->created_at?->format('Y-m-d H:i'),
        ];
    }

    private function renderImageThumb(CartItem $item): string
    {
        $url = $item->image_url;
        if (! is_string($url) || trim($url) === '') {
            return '<div class="avatar avatar-sm bg-label-secondary"><i class="icon-base ti tabler-photo"></i></div>';
        }
        $src = str_starts_with($url, 'http') ? $url : asset($url);
        return '<img src="' . e($src) . '" alt="Product" class="rounded" style="width:48px;height:48px;object-fit:cover;">';
    }

    private function formatShippingDestination(CartItem $item): string
    {
        $snapshot = is_array($item->shipping_snapshot) ? $item->shipping_snapshot : [];
        $cc = $snapshot['destination_country'] ?? null;
        $label = $snapshot['destination_label'] ?? null;
        $addrId = $snapshot['destination_address_id'] ?? null;

        if (! is_string($cc) || trim($cc) === '') {
            if (is_string($label) && trim($label) !== '') {
                return '<div class="small"><span class="text-muted">' . e($label) . '</span></div>';
            }

            return '<span class="text-muted">—</span>';
        }

        $cc = e(trim($cc));
        $labelHtml = is_string($label) && trim($label) !== ''
            ? '<div class="text-muted mt-1" style="max-width:14rem">' . e($label) . '</div>'
            : '';
        $idHint = $addrId !== null && $addrId !== ''
            ? '<div class="text-muted" style="font-size:0.7rem">#' . e((string) $addrId) . '</div>'
            : '';

        return '<div class="small">'
            . '<span class="badge bg-label-info">' . $cc . '</span>'
            . $labelHtml
            . $idHint
            . '</div>';
    }

    private function formatShippingBasis(CartItem $item): string
    {
        if ($item->shipping_snapshot === null) {
            return '<span class="badge bg-label-secondary text-muted"><i class="icon-base ti tabler-question-mark"></i> N/A</span>';
        }

        $snapshot = is_array($item->shipping_snapshot) ? $item->shipping_snapshot : [];
        $estimated = (bool) $item->estimated;
        $missing = $item->missing_fields ?? [];
        $carrier = $item->carrier ?: ($snapshot['carrier'] ?? 'auto');
        $mode = $item->pricing_mode ?: ($snapshot['pricing_mode'] ?? 'default');
        $mSrc = isset($snapshot['measurements_source']) ? (string) $snapshot['measurements_source'] : null;

        $parts = [];
        $parts[] = 'Carrier: ' . $carrier;
        $parts[] = 'Mode: ' . $mode;
        if ($mSrc === 'exact') {
            $parts[] = __('admin.measurements_imported_exact');
        } elseif ($mSrc === 'fallback') {
            $parts[] = __('admin.measurements_fallback_defaults');
        }
        if ($estimated) {
            $parts[] = 'Quote used fallback / incomplete inputs';
        }
        if (is_array($missing) && $missing !== []) {
            $parts[] = 'Missing: ' . implode(', ', $missing);
        }
        $tooltip = e(implode(' • ', $parts));

        $warn = $estimated || (is_array($missing) && $missing !== []) || $mSrc === 'fallback';

        if ($warn) {
            return '<span class="badge bg-label-warning text-warning" title="' . $tooltip . '"><i class="icon-base ti tabler-alert-circle"></i> '
                . e(__('admin.shipping_basis_estimated')) . '</span>';
        }

        return '<span class="badge bg-label-success text-success" title="' . $tooltip . '"><i class="icon-base ti tabler-check"></i> '
            . e(__('admin.shipping_basis_exact')) . '</span>';
    }

    public function updatePackage(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'weight_unit' => 'nullable|string|in:lb,g,kg',
            'dimension_unit' => 'nullable|string|in:in,cm',
        ]);

        $item = CartItem::findOrFail($id);

        $data = [];
        foreach (['weight', 'length', 'width', 'height'] as $key) {
            if (! $request->has($key)) {
                continue;
            }
            $raw = $request->input($key);
            if ($raw === null || $raw === '') {
                $data[$key] = null;
            } elseif (is_numeric($raw)) {
                $data[$key] = (float) $raw;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => __('admin.error'),
                ], 422);
            }
        }

        if ($request->has('weight_unit')) {
            $wu = $request->input('weight_unit');
            $data['weight_unit'] = is_string($wu) && trim($wu) !== '' ? trim($wu) : null;
        }
        if ($request->has('dimension_unit')) {
            $du = $request->input('dimension_unit');
            $data['dimension_unit'] = is_string($du) && trim($du) !== '' ? trim($du) : null;
        }

        if ($data === []) {
            return response()->json([
                'success' => false,
                'message' => __('admin.error'),
            ], 422);
        }

        $item->update($data);

        return response()->json([
            'success' => true,
            'message' => __('admin.package_updated'),
        ]);
    }

    public function updateShipping(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'shipping_cost' => 'required|numeric|min:0',
        ]);

        $item = CartItem::with('user')->findOrFail($id);
        $item->update(['shipping_cost' => $validated['shipping_cost']]);

        return response()->json(['success' => true, 'message' => __('admin.shipping_cost_updated'), 'shipping_cost' => (float) $item->shipping_cost]);
    }

    public function recalculateShipping(Request $request, int $id): JsonResponse
    {
        $item = CartItem::with('user')->findOrFail($id);
        $fresh = $this->cartShippingEstimate->recalculateAndPersist($item);

        if ($fresh->shipping_snapshot === null || $fresh->shipping_cost === null) {
            return response()->json([
                'success' => false,
                'message' => __('admin.shipping_recalculate_failed'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('admin.shipping_recalculated'),
            'shipping_cost' => (float) $fresh->shipping_cost,
        ]);
    }

    public function approveOrReject(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'review_status' => 'required|in:reviewed,rejected',
        ]);

        $item = CartItem::with('user')->where('review_status', 'pending_review')->findOrFail($id);
        $item->update($validated);

        $msg = $validated['review_status'] === 'reviewed' ? __('admin.item_approved') : __('admin.item_rejected');

        $this->notifyCartReviewUser(
            $item,
            title: $this->appNotificationTitle(),
            body: $msg . ' • ' . __('admin.view_cart'),
            important: $validated['review_status'] !== 'reviewed' // rejected is more urgent
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->route('admin.cart-review.index')->with('success', $msg);
    }

    private function notifyCartReviewUser(CartItem $item, string $title, string $body, bool $important = false): void
    {
        $user = $item->user;
        if (! $user) {
            return;
        }

        // 1) Persist in-app notification so it يظهر في التطبيق
        Notification::create([
            'user_id' => $user->id,
            'type' => 'orders',
            'title' => $title,
            'subtitle' => $body,
            'read' => false,
            'important' => $important,
            'action_label' => 'open_cart',
            'action_route' => '/cart',
        ]);

        // 2) Send FCM push (best-effort). Include deep-link data for the app.
        $imageUrl = $this->appIconUrl();
        $meta = [
            'route_key' => 'cart',
            'target_type' => 'cart',
            'action_label' => 'open_cart',
            'action_route' => '/cart',
        ];
        $this->dispatchService->sendToUser(
            $user,
            $title,
            $body,
            $imageUrl,
            null,
            $meta,
            null
        );
    }

    private function appNotificationTitle(): string
    {
        // Use dynamic app_name from admin/config/app-config, fallback "Eshterely"
        try {
            if (Schema::hasTable('app_config') && Schema::hasColumn('app_config', 'app_name')) {
                $row = DB::table('app_config')->first();
                $name = $row?->app_name ?? null;
                if (is_string($name) && trim($name) !== '') {
                    return trim($name);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return 'Eshterely';
    }

    private function appIconUrl(): ?string
    {
        // FCM supports image URL (shows as large image on Android / iOS as supported).
        try {
            if (Schema::hasTable('app_config') && Schema::hasColumn('app_config', 'app_icon_url')) {
                $row = DB::table('app_config')->first();
                $path = $row?->app_icon_url ?? null;
                if (! is_string($path) || trim($path) === '') {
                    return null;
                }
                $path = trim($path);
                return str_starts_with($path, 'http') ? $path : asset('storage/' . $path);
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }
}
