<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Payment\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderLineItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;
use App\Support\AdminFulfillmentLabels;
use App\Support\AdminOrderLineItemDisplay;
use App\Support\AdminUserDisplay;
use App\Support\AdminWarehouseReceiptDisplay;
use App\Support\AdminWarehouseReceiptImages;
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

        $queue = $this->normalizeWarehouseQueue($request->input('queue'));

        match ($queue) {
            'awaiting_arrival' => $query->where('fulfillment_status', OrderLineItem::FULFILLMENT_IN_TRANSIT_TO_WAREHOUSE),
            'ready_to_receive' => $query->where('fulfillment_status', OrderLineItem::FULFILLMENT_PURCHASED),
            'received' => $query->where('fulfillment_status', OrderLineItem::FULFILLMENT_ARRIVED_AT_WAREHOUSE),
            default => $query->where('fulfillment_status', OrderLineItem::FULFILLMENT_PURCHASED),
        };

        $showReceive = in_array($queue, ['awaiting_arrival', 'ready_to_receive'], true);
        $showEditReceive = $queue === 'received';

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

        $source = $request->input('source');
        if ($source === 'purchase_assistant') {
            $query->whereHas('shipment.order', fn ($q) => $q->whereNotNull('purchase_assistant_request_id'));
        } elseif ($source === 'standard') {
            $query->whereHas('shipment.order', fn ($q) => $q->whereNull('purchase_assistant_request_id'));
        }

        return DataTables::eloquent($query)
            ->addColumn('order_number', function (OrderLineItem $li) {
                $order = $li->shipment?->order;
                $on = $order?->order_number ?? '-';
                $oid = $li->shipment?->order_id;
                $badge = ($order && $order->purchase_assistant_request_id !== null)
                    ? '<span class="badge bg-label-info me-1">PA</span>'
                    : '';
                if ($oid && $on !== '-') {
                    return $badge.'<a href="'.route('admin.orders.show', $oid).'">'.e($on).'</a>';
                }

                return $badge.e($on);
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
            ->addColumn('intake', function (OrderLineItem $li) use ($queue) {
                if ($queue !== 'received') {
                    return '<span class="text-muted small">—</span>';
                }

                return AdminWarehouseReceiptDisplay::queueIntakeSummaryHtml($li->latestWarehouseReceipt);
            })
            ->addColumn('actions', function (OrderLineItem $li) use ($showReceive, $showEditReceive) {
                $order = route('admin.orders.show', $li->shipment->order_id);
                $on = e($li->shipment?->order?->order_number ?? '—');
                $pn = e(Str::limit($li->name, 80));
                $html = '<div class="d-flex flex-wrap gap-1 align-items-center">';
                if ($showReceive && AdminOrderLineItemDisplay::canReceiveIntoWarehouse($li)) {
                    $receiveUrl = route('admin.warehouse.receive', $li);
                    $html .= '<button type="button" class="btn btn-sm btn-primary js-wh-receive-modal" data-bs-toggle="modal" data-bs-target="#warehouseReceiveModal"'
                        .' data-receive-url="'.e($receiveUrl).'"'
                        .' data-order-number="'.$on.'"'
                        .' data-product-name="'.$pn.'">'
                        .e(__('admin.warehouse_receive')).'</button>';
                }
                if ($showEditReceive) {
                    $wr = $li->latestWarehouseReceipt;
                    if ($wr) {
                        $editUrl = route('admin.warehouse.receive-update', [$li, $wr]);
                        $imgPayload = [];
                        if (is_array($wr->images)) {
                            foreach ($wr->images as $entry) {
                                if (! is_string($entry) || $entry === '') {
                                    continue;
                                }
                                $imgPayload[] = [
                                    'raw' => $entry,
                                    'display' => AdminWarehouseReceiptImages::displayUrl($entry),
                                ];
                            }
                        }
                        $editPayload = [
                            'received_at' => $wr->received_at?->format('Y-m-d\TH:i'),
                            'received_weight' => $wr->received_weight,
                            'received_length' => $wr->received_length,
                            'received_width' => $wr->received_width,
                            'received_height' => $wr->received_height,
                            'additional_fee_amount' => $wr->additional_fee_amount !== null ? (float) $wr->additional_fee_amount : null,
                            'condition_notes' => $wr->condition_notes,
                            'special_handling_type' => $wr->special_handling_type,
                            'images' => $imgPayload,
                        ];
                        $b64 = base64_encode(json_encode($editPayload, JSON_UNESCAPED_UNICODE));
                        $html .= '<button type="button" class="btn btn-sm btn-outline-primary js-wh-receive-modal" data-bs-toggle="modal" data-bs-target="#warehouseReceiveModal"'
                            .' data-receive-url="'.e($editUrl).'"'
                            .' data-receive-edit-b64="'.e($b64).'"'
                            .' data-order-number="'.$on.'"'
                            .' data-product-name="'.$pn.'">'
                            .e(__('admin.warehouse_edit_receive')).'</button>';
                    }
                }
                $html .= '<a href="'.$order.'" class="btn btn-sm btn-outline-secondary">'.e(__('admin.source_order')).'</a>';
                $html .= '</div>';

                return $html;
            })
            ->rawColumns(['customer', 'order_number', 'product', 'fulfillment', 'intake', 'actions'])
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

    /**
     * DataTables may send `queue` as an empty string; Request::input() would then not fall back to the default,
     * which hid the Receive button while rows still matched the default (purchased) filter.
     */
    private function normalizeWarehouseQueue(mixed $queue): string
    {
        $q = is_string($queue) ? trim($queue) : '';
        $allowed = ['awaiting_arrival', 'ready_to_receive', 'received'];

        return in_array($q, $allowed, true) ? $q : 'ready_to_receive';
    }

    public function receiveForm(OrderLineItem $orderLineItem): View|RedirectResponse
    {
        if (! AdminOrderLineItemDisplay::canReceiveIntoWarehouse($orderLineItem)) {
            return redirect()
                ->route('admin.warehouse.index')
                ->with('error', __('admin.warehouse_receive_not_eligible'));
        }

        $orderLineItem->load(['shipment.order.user', 'latestWarehouseReceipt']);

        return view('admin.warehouse.receive', compact('orderLineItem'));
    }
}
