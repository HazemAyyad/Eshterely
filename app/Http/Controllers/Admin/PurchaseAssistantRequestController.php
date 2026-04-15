<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseAssistantRequest;
use App\Models\User;
use App\Services\PurchaseAssistant\PurchaseAssistantOrderFromRequestService;
use App\Services\PurchaseAssistant\PurchaseAssistantRequestNotifier;
use App\Support\AdminPurchaseAssistantDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class PurchaseAssistantRequestController extends Controller
{
    public function __construct(
        protected PurchaseAssistantOrderFromRequestService $orderFromRequestService,
        protected PurchaseAssistantRequestNotifier $notifier
    ) {}

    public function index(): View
    {
        return view('admin.purchase-assistant.index');
    }

    public function data(): JsonResponse
    {
        $query = PurchaseAssistantRequest::with('user')->orderByDesc('created_at');

        return DataTables::eloquent($query)
            ->addColumn('user_name', fn (PurchaseAssistantRequest $r) => $r->user?->full_name ?? $r->user?->name ?? '-')
            ->filterColumn('user_name', function ($query, $keyword) {
                $query->whereHas('user', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('full_name', 'like', "%{$keyword}%")
                        ->orWhere('display_name', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('store_name', fn (PurchaseAssistantRequest $r) => e($r->store_display_name ?: \App\Support\PurchaseAssistantStoreDisplayName::fromHost($r->source_domain)))
            ->filterColumn('store_name', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('store_display_name', 'like', "%{$keyword}%")
                        ->orWhere('source_domain', 'like', "%{$keyword}%");
                });
            })
            ->editColumn('created_at', fn (PurchaseAssistantRequest $r) => $r->created_at?->format('Y-m-d H:i') ?? '-')
            ->editColumn('status', fn (PurchaseAssistantRequest $r) => AdminPurchaseAssistantDataTable::statusBadge($r))
            ->addColumn('order', fn (PurchaseAssistantRequest $r) => $r->converted_order_id ? '<a href="'.e(route('admin.orders.show', $r->converted_order_id)).'">#'.e((string) $r->converted_order_id).'</a>' : '—')
            ->addColumn('link_icon', fn (PurchaseAssistantRequest $r) => AdminPurchaseAssistantDataTable::sourceLinkButton($r))
            ->addColumn('actions', function (PurchaseAssistantRequest $r) {
                $url = route('admin.purchase-assistant.show', $r);

                return '<a href="'.$url.'" class="btn btn-sm btn-primary">'.e(__('admin.details')).'</a>';
            })
            ->rawColumns(['status', 'order', 'link_icon', 'actions'])
            ->toJson();
    }

    public function show(PurchaseAssistantRequest $purchaseAssistantRequest): View
    {
        $purchaseAssistantRequest->load('user', 'convertedOrder');

        return view('admin.purchase-assistant.show', [
            'req' => $purchaseAssistantRequest,
        ]);
    }

    public function update(Request $request, PurchaseAssistantRequest $purchaseAssistantRequest): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:500',
            'details' => 'nullable|string|max:10000',
            'admin_product_price' => 'nullable|numeric|min:0',
            'admin_service_fee' => 'nullable|numeric|min:0',
            'admin_notes' => 'nullable|string|max:10000',
            'status' => 'nullable|in:'.implode(',', PurchaseAssistantRequest::statuses()),
            'action' => 'nullable|in:save,ready_for_payment',
        ]);

        $oldStatus = $purchaseAssistantRequest->status;
        $user = User::find($purchaseAssistantRequest->user_id);

        if (($validated['action'] ?? 'save') === 'ready_for_payment') {
            $request->validate([
                'admin_product_price' => 'required|numeric|min:0.01',
                'admin_service_fee' => 'required|numeric|min:0',
            ]);

            $purchaseAssistantRequest->fill([
                'title' => $validated['title'] ?? $purchaseAssistantRequest->title,
                'details' => $validated['details'] ?? $purchaseAssistantRequest->details,
                'admin_product_price' => $validated['admin_product_price'],
                'admin_service_fee' => $validated['admin_service_fee'],
                'admin_notes' => $validated['admin_notes'] ?? $purchaseAssistantRequest->admin_notes,
            ]);

            if ($purchaseAssistantRequest->converted_order_id === null) {
                $this->orderFromRequestService->createPendingPaymentOrder($purchaseAssistantRequest);
            }
            $purchaseAssistantRequest->refresh();

            if ($user) {
                $this->notifier->notifyAfterStatusChange(
                    $purchaseAssistantRequest,
                    $user,
                    $oldStatus,
                    $purchaseAssistantRequest->status
                );
            }
        } else {
            $purchaseAssistantRequest->fill([
                'title' => $validated['title'] ?? $purchaseAssistantRequest->title,
                'details' => $validated['details'] ?? $purchaseAssistantRequest->details,
                'admin_product_price' => $validated['admin_product_price'] ?? $purchaseAssistantRequest->admin_product_price,
                'admin_service_fee' => $validated['admin_service_fee'] ?? $purchaseAssistantRequest->admin_service_fee,
                'admin_notes' => $validated['admin_notes'] ?? $purchaseAssistantRequest->admin_notes,
            ]);
            if (isset($validated['status'])) {
                $purchaseAssistantRequest->status = $validated['status'];
            }
            $purchaseAssistantRequest->save();

            if ($user) {
                $this->notifier->notifyAfterStatusChange(
                    $purchaseAssistantRequest->fresh(),
                    $user,
                    $oldStatus,
                    $purchaseAssistantRequest->status
                );
            }
        }

        return redirect()
            ->route('admin.purchase-assistant.show', $purchaseAssistantRequest)
            ->with('success', __('admin.success'));
    }
}
