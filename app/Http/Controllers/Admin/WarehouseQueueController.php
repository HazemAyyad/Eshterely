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
use App\Support\AdminOrderLineItemDisplay;
use App\Support\AdminUserDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class WarehouseQueueController extends Controller
{
    public function index(Request $request): View
    {
        $base = $this->paidOrderLineItemsQuery();

        $counts = [
            'awaiting_arrival' => (clone $base)->where('fulfillment_status', OrderLineItem::FULFILLMENT_IN_TRANSIT_TO_WAREHOUSE)->count(),
            'ready_to_receive' => (clone $base)->where('fulfillment_status', OrderLineItem::FULFILLMENT_PURCHASED)->count(),
            'received' => (clone $base)->where('fulfillment_status', OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE)->count(),
        ];

        return view('admin.warehouse.index', compact('counts'));
    }

    public function data(Request $request): JsonResponse
    {
        $query = OrderLineItem::query()
            ->with(['shipment.order.user', 'latestWarehouseReceipt', 'cartItem', 'importedProduct'])
            ->whereHas('shipment.order', fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('status', PaymentStatus::Paid)));

        $queue = $request->get('queue', 'ready_to_receive');
        $showReceive = $queue === 'ready_to_receive';

        match ($queue) {
            'awaiting_arrival' => $query->where('fulfillment_status', OrderLineItem::FULFILLMENT_IN_TRANSIT_TO_WAREHOUSE),
            'ready_to_receive' => $query->where('fulfillment_status', OrderLineItem::FULFILLMENT_PURCHASED),
            'received' => $query->where('fulfillment_status', OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE),
            default => $query->where('fulfillment_status', OrderLineItem::FULFILLMENT_PURCHASED),
        };

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
            ->addColumn('order_number', function (OrderLineItem $li) {
                $on = $li->shipment?->order?->order_number ?? '-';
                $oid = $li->shipment?->order_id;
                if ($oid && $on !== '-') {
                    return '<a href="'.route('admin.orders.show', $oid).'">'.e($on).'</a>';
                }

                return e($on);
            })
            ->addColumn('customer', function (OrderLineItem $li) {
                $u = $li->shipment?->order?->user;
                if (! $u) {
                    return '—';
                }
                $name = e(AdminUserDisplay::primaryName($u));
                $phone = $u->phone ? '<div class="text-muted small">'.e($u->phone).'</div>' : '';

                return '<div><a href="'.route('admin.users.show', $u).'" class="fw-semibold">'.$name.'</a></div>'.$phone;
            })
            ->addColumn('product', fn (OrderLineItem $li) => AdminOrderLineItemDisplay::adminProductThumbnailWithNameHtml($li, 40, 60))
            ->addColumn('store_name', fn (OrderLineItem $li) => e($li->store_name ?? '-'))
            ->addColumn('fulfillment', function (OrderLineItem $li) {
                $p = AdminFulfillmentLabels::lineItemFulfillment($li->fulfillment_status);

                return '<span class="badge bg-'.$p['badge'].'">'.$p['label'].'</span>';
            })
            ->addColumn('store_tracking', function (OrderLineItem $li) {
                $t = $li->review_metadata['store_tracking'] ?? '';

                return $t !== '' ? e(Str::limit($t, 24)) : '—';
            })
            ->addColumn('actions', function (OrderLineItem $li) use ($showReceive) {
                $order = route('admin.orders.show', $li->shipment->order_id);
                $on = e($li->shipment?->order?->order_number ?? '—');
                $pn = e(Str::limit($li->name, 80));
                $html = '<div class="d-flex flex-wrap gap-1 align-items-center">';
                if ($showReceive) {
                    $receiveUrl = route('admin.warehouse.receive', $li);
                    $html .= '<button type="button" class="btn btn-sm btn-primary js-wh-receive-modal" data-bs-toggle="modal" data-bs-target="#warehouseReceiveModal"'
                        .' data-receive-url="'.e($receiveUrl).'"'
                        .' data-order-number="'.$on.'"'
                        .' data-product-name="'.$pn.'">'
                        .e(__('admin.warehouse_receive')).'</button>';
                }
                $html .= '<a href="'.$order.'" class="btn btn-sm btn-outline-secondary">'.e(__('admin.source_order')).'</a>';
                $html .= '</div>';

                return $html;
            })
            ->rawColumns(['customer', 'order_number', 'product', 'fulfillment', 'actions'])
            ->toJson();
    }

    /**
     * Line items tied to paid orders (same scope as warehouse queues / tab counts).
     */
    private function paidOrderLineItemsQuery(): Builder
    {
        return OrderLineItem::query()
            ->whereHas('shipment.order', fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('status', PaymentStatus::Paid)));
    }

    public function receiveForm(OrderLineItem $orderLineItem): View
    {
        $orderLineItem->load(['shipment.order.user', 'latestWarehouseReceipt']);

        return view('admin.warehouse.receive', compact('orderLineItem'));
    }
}
