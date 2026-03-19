<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Notification;
use App\Services\Fcm\NotificationDispatchService;
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
        protected NotificationDispatchService $dispatchService
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
            ->addColumn('weight_dims', fn (CartItem $item) => $this->formatWeightDims($item))
            ->addColumn('shipping_basis', fn (CartItem $item) => $this->formatShippingBasis($item))
            ->addColumn('shipping_cost_edit', function (CartItem $item) {
                $url = route('admin.cart-review.shipping', $item->id);
                $val = $item->shipping_cost !== null ? number_format((float) $item->shipping_cost, 2) : '';
                return '<input type="number" step="0.01" min="0" class="form-control form-control-sm d-inline-block shipping-cost-input" style="width:80px" data-id="' . $item->id . '" data-url="' . $url . '" value="' . e($val) . '" placeholder="0"> <button type="button" class="btn btn-sm btn-outline-primary btn-save-shipping ms-1" data-id="' . $item->id . '" data-url="' . $url . '">' . __('admin.save') . '</button>';
            })
            ->editColumn('created_at', fn (CartItem $item) => $item->created_at?->format('Y-m-d H:i'))
            ->addColumn('actions', function (CartItem $item) {
                $approveUrl = route('admin.cart-review.update', $item->id);
                $reviewBtns = $item->review_status === 'pending_review'
                    ? '<button type="button" class="btn btn-sm btn-success btn-approve" data-url="' . e($approveUrl) . '" data-status="reviewed">' . __('admin.approve') . '</button> ' .
                      '<button type="button" class="btn btn-sm btn-danger btn-reject" data-url="' . e($approveUrl) . '" data-status="rejected">' . __('admin.reject') . '</button> '
                    : '<span class="badge bg-' . ($item->review_status === 'reviewed' ? 'success' : 'secondary') . '">' . e($item->review_status) . '</span>';
                return '<button type="button" class="btn btn-sm btn-info btn-details me-1" data-details="' . e(json_encode($this->itemDetailsForModal($item))) . '">' . __('admin.details') . '</button> ' . $reviewBtns;
            })
            ->rawColumns(['image', 'shipping_basis', 'actions', 'shipping_cost_edit'])
            ->toJson();
    }

    private function formatWeightDims(CartItem $item): string
    {
        $parts = [];
        if ($item->weight !== null && (float) $item->weight > 0) {
            $parts[] = (float) $item->weight . ($item->weight_unit ?? '');
        }
        if ($item->length !== null || $item->width !== null || $item->height !== null) {
            $parts[] = ($item->length ?? '-') . '×' . ($item->width ?? '-') . '×' . ($item->height ?? '-') . ' ' . ($item->dimension_unit ?? '');
        }
        return $parts ? implode(' / ', $parts) : '-';
    }

    private function itemDetailsForModal(CartItem $item): array
    {
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

        $parts = [];
        $parts[] = 'Carrier: ' . $carrier;
        $parts[] = 'Mode: ' . $mode;
        if ($estimated) {
            $parts[] = 'Estimated based on fallback data';
        }
        if (is_array($missing) && $missing !== []) {
            $parts[] = 'Missing: ' . implode(', ', $missing);
        }
        $tooltip = e(implode(' • ', $parts));

        if ($estimated || (is_array($missing) && $missing !== [])) {
            return '<span class="badge bg-label-warning text-warning" title="' . $tooltip . '"><i class="icon-base ti tabler-alert-circle"></i> Estimated</span>';
        }

        return '<span class="badge bg-label-success text-success" title="' . $tooltip . '"><i class="icon-base ti tabler-check"></i> Exact</span>';
    }

    public function updateShipping(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'shipping_cost' => 'required|numeric|min:0',
        ]);

        $item = CartItem::with('user')->findOrFail($id);
        $item->update(['shipping_cost' => $validated['shipping_cost']]);

        $this->notifyCartReviewUser(
            $item,
            title: $this->appNotificationTitle(),
            body: __('admin.shipping_cost_updated') . ' • ' . __('admin.view_cart'),
            important: false
        );

        return response()->json(['success' => true, 'message' => __('admin.shipping_cost_updated'), 'shipping_cost' => (float) $item->shipping_cost]);
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
