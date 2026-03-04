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
            ->where('review_status', 'pending_review')
            ->orderBy('created_at', 'desc');

        return DataTables::eloquent($query)
            ->editColumn('name', fn (CartItem $item) => \Str::limit($item->name, 40))
            ->editColumn('store_name', fn (CartItem $item) => $item->store_name ?? '-')
            ->addColumn('user_contact', fn (CartItem $item) => $item->user?->phone ?? $item->user?->email ?? '-')
            ->editColumn('unit_price', fn (CartItem $item) => number_format((float) $item->unit_price, 2) . ' ' . $item->currency)
            ->editColumn('created_at', fn (CartItem $item) => $item->created_at?->format('Y-m-d'))
            ->addColumn('actions', function (CartItem $item) {
                $approveUrl = route('admin.cart-review.update', $item->id);
                return '<button type="button" class="btn btn-sm btn-success btn-approve" data-url="' . $approveUrl . '" data-status="reviewed">' . __('admin.approve') . '</button> ' .
                    '<button type="button" class="btn btn-sm btn-danger btn-reject" data-url="' . $approveUrl . '" data-status="rejected">' . __('admin.reject') . '</button>';
            })
            ->rawColumns(['actions'])
            ->toJson();
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
