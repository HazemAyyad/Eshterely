<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.orders.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = Order::with('user')->orderBy('placed_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('origin')) {
            $query->where('origin', $request->origin);
        }

        return DataTables::eloquent($query)
            ->addColumn('user_contact', fn (Order $o) => $o->user?->phone ?? $o->user?->email ?? '-')
            ->editColumn('status', fn (Order $o) => '<span class="badge bg-' . ($o->status === 'delivered' ? 'success' : ($o->status === 'cancelled' ? 'danger' : 'warning')) . '">' . $o->status . '</span>')
            ->editColumn('total_amount', fn (Order $o) => number_format((float) $o->total_amount, 2) . ' ' . $o->currency)
            ->editColumn('placed_at', fn (Order $o) => $o->placed_at?->format('Y-m-d') ?? '-')
            ->addColumn('actions', fn (Order $o) => '<a href="' . route('admin.orders.show', $o) . '" class="btn btn-text-secondary rounded-pill waves-effect btn-icon" title="' . __('admin.show') . '"><i class="icon-base ti tabler-eye icon-22px"></i></a>')
            ->rawColumns(['status', 'actions'])
            ->filterColumn('order_number', fn ($q, $keyword) => $q)
            ->filterColumn('user_contact', fn ($q, $keyword) => $q->where(function ($q2) use ($keyword) {
                $q2->where('order_number', 'like', "%{$keyword}%")
                    ->orWhereHas('user', fn ($u) => $u->where('phone', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%"));
            }))
            ->toJson();
    }

    public function show(Order $order): View
    {
        $order->load(['shipments.lineItems', 'shipments.trackingEvents', 'user', 'payments']);
        $priceLines = DB::table('order_price_lines')->where('order_id', $order->id)->get();

        return view('admin.orders.show', compact('order', 'priceLines'));
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:in_transit,delivered,cancelled',
        ]);

        $order->update($validated);

        if ($validated['status'] === 'delivered') {
            $order->update(['delivered_at' => now()]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }

        return redirect()->route('admin.orders.show', $order)->with('success', __('admin.success'));
    }
}
