<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletRefundRequest;
use App\Services\Wallet\WalletRefundRequestNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class WalletRefundRequestsController extends Controller
{
    public function __construct(
        protected WalletRefundRequestNotifier $notifier
    ) {}

    public function index(): View
    {
        return view('admin.wallet-refunds.index');
    }

    public function data()
    {
        $query = WalletRefundRequest::query()->with('user')->orderByDesc('id');

        return DataTables::eloquent($query)
            ->addColumn('user_contact', fn (WalletRefundRequest $r) => $r->user?->email ?? $r->user?->phone ?? 'User #'.$r->user_id)
            ->addColumn('amount_fmt', fn (WalletRefundRequest $r) => number_format((float) $r->amount, 2).' '.$r->currency)
            ->editColumn('created_at', fn (WalletRefundRequest $r) => $r->created_at?->format('Y-m-d H:i') ?? '')
            ->addColumn('actions', fn (WalletRefundRequest $r) => '<a href="'.route('admin.wallet-refunds.show', $r).'" class="btn btn-sm btn-primary">'.e(__('admin.details')).'</a>')
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function show(WalletRefundRequest $walletRefundRequest): View
    {
        $walletRefundRequest->load(['user', 'reviewer']);

        return view('admin.wallet-refunds.show', ['requestModel' => $walletRefundRequest]);
    }

    public function updateStatus(Request $request, WalletRefundRequest $walletRefundRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', WalletRefundRequest::statuses()),
            'admin_notes' => 'nullable|string|max:10000',
        ]);

        $old = $walletRefundRequest->status;
        $new = $validated['status'];

        $walletRefundRequest->status = $new;
        if (array_key_exists('admin_notes', $validated)) {
            $walletRefundRequest->admin_notes = $validated['admin_notes'];
        }
        $walletRefundRequest->reviewed_by = auth('admin')->id();
        if ($walletRefundRequest->reviewed_at === null) {
            $walletRefundRequest->reviewed_at = now();
        }

        if ($new === WalletRefundRequest::STATUS_PROCESSED && $walletRefundRequest->processed_at === null) {
            $walletRefundRequest->processed_at = now();
        }
        if ($new === WalletRefundRequest::STATUS_TRANSFERRED && $walletRefundRequest->transferred_at === null) {
            $walletRefundRequest->transferred_at = now();
        }

        $walletRefundRequest->save();
        $fresh = $walletRefundRequest->fresh();

        if ($old !== $new) {
            if ($new === WalletRefundRequest::STATUS_APPROVED) {
                $this->notifier->notifyApproved($fresh);
            } elseif ($new === WalletRefundRequest::STATUS_REJECTED) {
                $this->notifier->notifyRejected($fresh);
            } elseif ($new === WalletRefundRequest::STATUS_TRANSFERRED) {
                $this->notifier->notifyTransferred($fresh);
            }
        }

        return redirect()
            ->route('admin.wallet-refunds.show', $walletRefundRequest)
            ->with('success', __('admin.wallet_refund_status_updated'));
    }
}
