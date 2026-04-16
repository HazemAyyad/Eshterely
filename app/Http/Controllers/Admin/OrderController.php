<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\OrderShipmentEvent;
use App\Support\AdminOrderFulfillmentPresenter;
use App\Support\AdminUserDisplay;
use App\Support\OrderExecutionStatus;
use App\Services\Admin\AdminOrderOperationService;
use App\Services\Admin\OrderStatusWorkflowService;
use App\Services\Admin\ShipmentOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class OrderController extends Controller
{
    public function __construct(
        protected OrderStatusWorkflowService $workflow,
        protected AdminOrderOperationService $operationService,
        protected ShipmentOperationService $shipmentService
    ) {}

    public function index(Request $request): View
    {
        return view('admin.orders.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = Order::with([
            'user',
            'payments',
            'lineItems.shipmentItems.shipment',
        ])
            ->orderByDesc('updated_at');

        if ($request->filled('execution_status')) {
            $exec = (string) $request->input('execution_status');
            if (in_array($exec, OrderExecutionStatus::filterableExecutionStatuses(), true)) {
                $ids = OrderExecutionStatus::matchingOrderIdsForExecutionFilter($exec);
                if ($ids === []) {
                    $query->whereRaw('0 = 1');
                } else {
                    $query->whereIn('orders.id', $ids);
                }
            }
        }
        if ($request->filled('origin')) {
            $query->where('origin', $request->origin);
        }
        if ($request->filled('source')) {
            $src = (string) $request->input('source');
            if ($src === 'purchase_assistant') {
                $query->whereNotNull('purchase_assistant_request_id');
            } elseif ($src === 'standard') {
                $query->whereNull('purchase_assistant_request_id');
            }
        }

        return DataTables::eloquent($query)
            ->editColumn('order_number', function (Order $o) {
                $num = e($o->order_number);
                $link = '<a href="'.route('admin.orders.show', $o).'" class="fw-semibold">'.$num.'</a>';
                if ($o->purchase_assistant_request_id !== null) {
                    return '<span class="badge bg-label-info me-1">PA</span>'.$link;
                }

                return $link;
            })
            ->addColumn('customer', function (Order $o) {
                $u = $o->user;
                if (! $u) {
                    return '—';
                }
                $name = e(AdminUserDisplay::primaryName($u));
                $phone = $u->phone ? '<div class="text-muted small">'.e($u->phone).'</div>' : '';

                return '<div><a href="'.route('admin.users.show', $u).'" class="fw-semibold">'.$name.'</a></div>'.$phone;
            })
            ->addColumn('execution_status', function (Order $o) {
                $hasPaid = $o->payments->contains(fn ($p) => $p->status->value === 'paid');
                $exec = OrderExecutionStatus::resolve($o, $o->lineItems, $hasPaid);
                $badge = OrderExecutionStatus::badgeClass($exec);

                return '<span class="badge bg-'.$badge.'">'.e(__('admin.execution_status_'.$exec)).'</span>';
            })
            ->addColumn('payment_status', function (Order $o) {
                if ($o->status === Order::STATUS_PAID) {
                    return '<span class="badge bg-success">paid</span>';
                }
                $paid = $o->payments->contains(fn ($p) => $p->status->value === 'paid');

                return $paid ? '<span class="badge bg-success">paid</span>' : '<span class="badge bg-secondary">pending</span>';
            })
            ->addColumn('order_total_snapshot', fn (Order $o) => $o->order_total_snapshot !== null ? number_format((float) $o->order_total_snapshot, 2).' '.$o->currency : number_format((float) $o->total_amount, 2).' '.$o->currency)
            ->addColumn('paid_at', function (Order $o) {
                $p = $o->payments->filter(fn ($x) => $x->paid_at !== null)->sortBy('paid_at')->first();

                return $p && $p->paid_at ? e($p->paid_at->format('Y-m-d H:i')) : '—';
            })
            ->editColumn('placed_at', fn (Order $o) => $o->placed_at?->format('Y-m-d H:i') ?? '—')
            ->addColumn('actions', fn (Order $o) => '<a href="'.route('admin.orders.show', $o).'" class="btn btn-sm btn-primary">'.e(__('admin.view')).'</a>')
            ->rawColumns(['order_number', 'customer', 'execution_status', 'payment_status', 'actions'])
            ->filterColumn('order_number', fn ($q, $keyword) => $q)
            ->filterColumn('customer', function ($q, $keyword) {
                $q->where(function ($q2) use ($keyword) {
                    $q2->where('order_number', 'like', "%{$keyword}%")
                        ->orWhereHas('user', function ($u) use ($keyword) {
                            $u->where('phone', 'like', "%{$keyword}%")
                                ->orWhere('email', 'like', "%{$keyword}%")
                                ->orWhere('name', 'like', "%{$keyword}%")
                                ->orWhere('full_name', 'like', "%{$keyword}%")
                                ->orWhere('display_name', 'like', "%{$keyword}%");
                        });
                });
            })
            ->toJson();
    }

    public function show(Order $order): View
    {
        $order->load([
            'lineItems.shipment.order',
            'lineItems.cartItem',
            'lineItems.importedProduct',
            'lineItems.shipmentItems.shipment.payments',
            'lineItems.latestWarehouseReceipt',
            'user',
            'payments.attempts',
            'payments.events',
            'operationLogs.admin',
        ]);
        $priceLines = DB::table('order_price_lines')->where('order_id', $order->id)->get();
        $canCancelOrder = $this->workflow->canTransitionTo($order, Order::STATUS_CANCELLED)['allowed'];
        $items = $order->lineItems;
        $hasPaidCheckout = $order->payments->contains(fn ($p) => $p->status->value === 'paid');

        $fulfillmentPresented = AdminOrderFulfillmentPresenter::forOrder($items, $hasPaidCheckout);
        $fulfillmentSummary = $fulfillmentPresented['fulfillment_summary'];
        $fulfillmentStages = $fulfillmentPresented['fulfillment_stages'];
        $executionStatus = OrderExecutionStatus::resolve($order, $items, $hasPaidCheckout);

        $outboundShipments = $order->lineItems
            ->flatMap(fn ($li) => $li->shipmentItems)
            ->map(fn ($si) => $si->shipment)
            ->filter()
            ->unique('id')
            ->values();

        $customerDisplayName = null;
        if ($order->user) {
            $u = $order->user;
            $customerDisplayName = trim((string) ($u->full_name ?: $u->display_name ?: $u->name));
            if ($customerDisplayName === '') {
                $customerDisplayName = $u->email ?? $u->phone ?? ('#'.$u->id);
            }
        }

        return view('admin.orders.show', compact(
            'order',
            'priceLines',
            'canCancelOrder',
            'fulfillmentSummary',
            'fulfillmentStages',
            'executionStatus',
            'outboundShipments',
            'customerDisplayName'
        ));
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        $allowed = $this->workflow->allStatuses();
        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', $allowed),
        ]);

        try {
            $this->operationService->updateStatus($order, $request->user('admin'), $validated['status']);
        } catch (\InvalidArgumentException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->route('admin.orders.show', $order)->with('error', $e->getMessage());
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }
        return redirect()->route('admin.orders.show', $order)->with('success', __('admin.success'));
    }

    public function review(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $this->operationService->markAsReviewed(
            $order,
            $request->user('admin'),
            $validated['admin_notes'] ?? null
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }
        return redirect()->route('admin.orders.show', $order)->with('success', __('admin.success'));
    }

    public function shippingOverride(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        $shipmentIds = $order->shipments->pluck('id')->toArray();
        $validated = $request->validate([
            'order_shipment_id' => 'required|exists:order_shipments,id|in:' . implode(',', $shipmentIds),
            'shipping_override_amount' => 'nullable|numeric|min:0',
            'shipping_override_carrier' => 'nullable|string|max:50',
            'shipping_override_notes' => 'nullable|string|max:1000',
        ]);

        $shipment = OrderShipment::findOrFail($validated['order_shipment_id']);
        $this->operationService->applyShippingOverride(
            $shipment,
            $request->user('admin'),
            isset($validated['shipping_override_amount']) ? (float) $validated['shipping_override_amount'] : null,
            $validated['shipping_override_carrier'] ?? null,
            $validated['shipping_override_notes'] ?? null
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }
        return redirect()->route('admin.orders.show', $order)->with('success', __('admin.success'));
    }

    public function updateShipment(Request $request, Order $order, OrderShipment $shipment): RedirectResponse|JsonResponse
    {
        if ($shipment->order_id !== $order->id) {
            abort(404);
        }

        $validated = $request->validate([
            'carrier' => 'nullable|string|max:50',
            'tracking_number' => 'nullable|string|max:191',
            'shipment_status' => 'nullable|string|max:50',
            'estimated_delivery_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $admin = $request->user('admin');
            $s = $shipment;
            if (! empty($validated['carrier'])) {
                $s = $this->shipmentService->assignCarrier($s, $admin, $validated['carrier']);
            }
            if (array_key_exists('tracking_number', $validated) && $validated['tracking_number'] !== null && $validated['tracking_number'] !== '') {
                $s = $this->shipmentService->assignTrackingNumber($s, $admin, $validated['tracking_number']);
            }
            if (! empty($validated['shipment_status'])) {
                $s = $this->shipmentService->updateShipmentStatus($s, $admin, $validated['shipment_status']);
            }
            if (array_key_exists('estimated_delivery_at', $validated)) {
                $at = $validated['estimated_delivery_at'] ? new \DateTimeImmutable($validated['estimated_delivery_at']) : null;
                $s = $this->shipmentService->setEstimatedDelivery($s, $admin, $at);
            }
            if (array_key_exists('notes', $validated)) {
                $s->update(['notes' => $validated['notes']]);
            }
        } catch (\InvalidArgumentException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->route('admin.orders.show', $order)->with('error', $e->getMessage());
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }
        return redirect()->route('admin.orders.show', $order)->with('success', __('admin.success'));
    }

    public function addShipmentEvent(Request $request, Order $order, OrderShipment $shipment): RedirectResponse|JsonResponse
    {
        if ($shipment->order_id !== $order->id) {
            abort(404);
        }

        $validated = $request->validate([
            'event_type' => 'required|string|in:' . implode(',', OrderShipmentEvent::eventTypes()),
            'event_label' => 'nullable|string|max:191',
            'event_time' => 'nullable|date',
            'location' => 'nullable|string|max:191',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $eventTime = ! empty($validated['event_time']) ? new \DateTimeImmutable($validated['event_time']) : null;
            $this->shipmentService->appendEvent(
                $shipment,
                $request->user('admin'),
                $validated['event_type'],
                $validated['event_label'] ?? null,
                $eventTime,
                $validated['location'] ?? null,
                null,
                $validated['notes'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->route('admin.orders.show', $order)->with('error', $e->getMessage());
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }
        return redirect()->route('admin.orders.show', $order)->with('success', __('admin.success'));
    }

    public function markShipmentDelivered(Request $request, Order $order, OrderShipment $shipment): RedirectResponse|JsonResponse
    {
        if ($shipment->order_id !== $order->id) {
            abort(404);
        }

        try {
            $this->shipmentService->markDelivered($shipment, $request->user('admin'));
        } catch (\InvalidArgumentException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->route('admin.orders.show', $order)->with('error', $e->getMessage());
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }
        return redirect()->route('admin.orders.show', $order)->with('success', __('admin.success'));
    }
}
