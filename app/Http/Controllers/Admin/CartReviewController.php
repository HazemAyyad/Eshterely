<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CartReviewController extends Controller
{
    public function index(): View
    {
        return view('admin.cart-review.index');
    }

    public function data(): JsonResponse
    {
        $query = CartItem::with('user')
            ->orderBy('created_at', 'desc');

        return DataTables::eloquent($query)
            ->editColumn('name', fn (CartItem $item) => \Str::limit($item->name, 40))
            ->editColumn('store_name', fn (CartItem $item) => $item->store_name ?? '-')
            ->addColumn('user_name', fn (CartItem $item) => $item->user?->full_name ?? $item->user?->name ?? '-')
            ->addColumn('user_contact', fn (CartItem $item) => $item->user?->phone ?? $item->user?->email ?? '-')
            ->editColumn('unit_price', fn (CartItem $item) => number_format((float) $item->unit_price, 2) . ' ' . $item->currency)
            ->addColumn('variation_text', fn (CartItem $item) => $item->variation_text ? \Str::limit($item->variation_text, 30) : '-')
            ->addColumn('weight_dims', fn (CartItem $item) => $this->formatWeightDims($item))
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
            ->rawColumns(['actions', 'shipping_cost_edit'])
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
            'created_at' => $item->created_at?->format('Y-m-d H:i'),
        ];
    }

    public function updateShipping(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'shipping_cost' => 'required|numeric|min:0',
        ]);

        $item = CartItem::findOrFail($id);
        $item->update(['shipping_cost' => $validated['shipping_cost']]);

        return response()->json(['success' => true, 'message' => __('admin.shipping_cost_updated'), 'shipping_cost' => (float) $item->shipping_cost]);
    }

    public function approveOrReject(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'review_status' => 'required|in:reviewed,rejected',
        ]);

        $item = CartItem::where('review_status', 'pending_review')->findOrFail($id);
        $item->update($validated);

        $msg = $validated['review_status'] === 'reviewed' ? __('admin.item_approved') : __('admin.item_rejected');

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->route('admin.cart-review.index')->with('success', $msg);
    }
}
