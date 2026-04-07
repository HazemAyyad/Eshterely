<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\OrderShipmentEvent;
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
        $query = Order::with('user', 'payments', 'shipments.lineItems')
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('origin')) {
            $query->where('origin', $request->origin);
        }

        return DataTables::eloquent($query)
            ->addColumn('user_contact', fn (Order $o) => $o->user?->phone ?? $o->user?->email ?? '-')
            ->addColumn('payment_status', function (Order $o) {
                if ($o->status === Order::STATUS_PAID) {
                    return '<span class="badge bg-success">paid</span>';
                }
                $paid = $o->payments->contains(fn ($p) => $p->status->value === 'paid');
                return $paid ? '<span class="badge bg-success">paid</span>' : '<span class="badge bg-secondary">pending</span>';
            })
            ->editColumn('status', fn (Order $o) => '<span class="badge bg-' . $this->statusBadgeClass($o->status) . '">' . e($o->status) . '</span>')
            ->addColumn('estimated', fn (Order $o) => $o->estimated ? '<span class="badge bg-info">estimated</span>' : '-')
            ->addColumn('needs_review', fn (Order $o) => $o->needs_review ? '<span class="badge bg-warning">review</span>' : '-')
            ->addColumn('order_total_snapshot', fn (Order $o) => $o->order_total_snapshot !== null ? number_format((float) $o->order_total_snapshot, 2) . ' ' . $o->currency : number_format((float) $o->total_amount, 2) . ' ' . $o->currency)
            ->addColumn('payment_reference', function (Order $o) {
                $paid = $o->payments->first(fn ($p) => $p->paid_at);
                return $paid !== null ? $paid->reference : '-';
            })
            ->addColumn('source_carrier', function (Order $o) {
                $first = $o->shipments->flatMap(fn ($s) => $s->lineItems)->first();
                if (! $first) {
                    return '-';
                }
                $carrier = $first->review_metadata['carrier'] ?? $first->pricing_snapshot['carrier'] ?? null;
                return $carrier ? e($carrier) : '-';
            })
            ->editColumn('total_amount', fn (Order $o) => number_format((float) $o->total_amount, 2) . ' ' . $o->currency)
            ->editColumn('placed_at', fn (Order $o) => $o->placed_at?->format('Y-m-d') ?? '-')
            ->addColumn('actions', fn (Order $o) => '<a href="' . route('admin.orders.show', $o) . '" class="btn btn-text-secondary rounded-pill waves-effect btn-icon" title="' . __('admin.show') . '"><i class="icon-base ti tabler-eye icon-22px"></i></a>')
            ->rawColumns(['status', 'payment_status', 'estimated', 'needs_review', 'actions'])
            ->filterColumn('order_number', fn ($q, $keyword) => $q)
            ->filterColumn('user_contact', fn ($q, $keyword) => $q->where(function ($q2) use ($keyword) {
                $q2->where('order_number', 'like', "%{$keyword}%")
                    ->orWhereHas('user', fn ($u) => $u->where('phone', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%"));
            }))
            ->toJson();
    }

    public function show(Order $order): View
    {
        $order->load([
            'shipments.lineItems',
            'shipments.trackingEvents',
            'shipments.events',
            'lineItems.shipment.order',
            'lineItems.shipmentItems.shipment',
            'lineItems.latestWarehouseReceipt',
            'user',
            'payments.attempts',
            'payments.events',
            'operationLogs.admin',
        ]);
        $priceLines = DB::table('order_price_lines')->where('order_id', $order->id)->get();
        $allowedStatuses = $this->workflow->allStatuses();
        $canTransitionTo = [];
        foreach ($allowedStatuses as $s) {
            $check = $this->workflow->canTransitionTo($order, $s);
            if ($check['allowed']) {
                $canTransitionTo[] = $s;
            }
        }
        $shipmentEventTypes = OrderShipmentEvent::eventTypes();

        return view('admin.orders.show', compact('order', 'priceLines', 'allowedStatuses', 'canTransitionTo', 'shipmentEventTypes'));
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

    private function statusBadgeClass(string $status): string
    {
        return match ($status) {
            Order::STATUS_DELIVERED => 'success',
            Order::STATUS_CANCELLED => 'danger',
            Order::STATUS_PAID, Order::STATUS_APPROVED => 'primary',
            Order::STATUS_PENDING_PAYMENT => 'secondary',
            default => 'warning',
        };
    }
}
