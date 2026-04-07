<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Payment\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderLineItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;
use App\Support\AdminFulfillmentLabels;
use Illuminate\Support\Str;

class WarehouseQueueController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.warehouse.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = OrderLineItem::query()
            ->with(['shipment.order.user', 'latestWarehouseReceipt'])
            ->whereHas('shipment.order', fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('status', PaymentStatus::Paid)));

        $queue = $request->get('queue', 'awaiting');

        if ($queue === 'awaiting') {
            $query->whereIn('fulfillment_status', [
                OrderLineItem::FULFILLMENT_PURCHASED,
                OrderLineItem::FULFILLMENT_IN_TRANSIT_TO_WAREHOUSE,
            ]);
        } elseif ($queue === 'received') {
            $query->whereIn('fulfillment_status', [
                OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE,
                OrderLineItem::FULFILLMENT_READY_FOR_SHIPMENT,
            ]);
        } elseif ($queue === 'special') {
            $query->whereHas('warehouseReceipts', fn ($q) => $q->whereNotNull('special_handling_type')->where('special_handling_type', '!=', ''));
        }

        if ($request->filled('user_id')) {
            $query->whereHas('shipment.order', fn ($q) => $q->where('user_id', (int) $request->user_id));
        }

        if ($request->filled('order_number')) {
            $on = trim((string) $request->order_number);
            $query->whereHas('shipment.order', fn ($q) => $q->where('order_number', 'like', "%{$on}%"));
        }

        if ($request->filled('store')) {
            $st = trim((string) $request->store);
            $query->where('store_name', 'like', "%{$st}%");
        }

        return DataTables::eloquent($query)
            ->addColumn('order_number', fn (OrderLineItem $li) => e($li->shipment?->order?->order_number ?? '-'))
            ->addColumn('customer', function (OrderLineItem $li) {
                $u = $li->shipment?->order?->user;
                if (! $u) {
                    return '-';
                }
                $c = e($u->phone ?? $u->email ?? ('#'.$u->id));

                return '<a href="'.route('admin.users.show', $u).'">'.$c.'</a>';
            })
            ->addColumn('product', fn (OrderLineItem $li) => e(Str::limit($li->name, 60)))
            ->addColumn('store_name', fn (OrderLineItem $li) => e($li->store_name ?? '-'))
            ->addColumn('fulfillment', function (OrderLineItem $li) {
                $p = AdminFulfillmentLabels::lineItemFulfillment($li->fulfillment_status);

                return '<span class="badge bg-'.$p['badge'].'">'.$p['label'].'</span>';
            })
            ->addColumn('store_tracking', function (OrderLineItem $li) {
                $t = $li->review_metadata['store_tracking'] ?? '';

                return $t !== '' ? e(Str::limit($t, 24)) : '—';
            })
            ->addColumn('actions', function (OrderLineItem $li) {
                $receive = route('admin.warehouse.receive-form', $li);
                $order = route('admin.orders.show', $li->shipment->order_id);

                return '<div class="d-flex flex-wrap gap-1">'
                    .'<a href="'.$receive.'" class="btn btn-sm btn-primary">'.e(__('admin.warehouse_receive')).'</a>'
                    .'<a href="'.$order.'" class="btn btn-sm btn-outline-secondary">'.e(__('admin.source_order')).'</a>'
                    .'</div>';
            })
            ->rawColumns(['customer', 'fulfillment', 'actions'])
            ->toJson();
    }

    public function receiveForm(OrderLineItem $orderLineItem): View
    {
        $orderLineItem->load(['shipment.order.user', 'latestWarehouseReceipt']);

        return view('admin.warehouse.receive', compact('orderLineItem'));
    }
}
