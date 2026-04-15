<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletRefund;
use App\Services\Wallet\WalletRefundNotifier;
use App\Services\Wallet\WalletRefundProcessor;
use App\Support\AdminWalletDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class WalletRefundsController extends Controller
{
    public function __construct(
        protected WalletRefundNotifier $notifier,
        protected WalletRefundProcessor $refundProcessor
    ) {}

    public function index(): View
    {
        return view('admin.wallet-refunds.index');
    }

    public function data()
    {
        $query = WalletRefund::query()->with('user')->orderByDesc('id');

        return DataTables::eloquent($query)
            ->addColumn('customer', fn (WalletRefund $r) => AdminWalletDataTable::customerCell($r->user))
            ->addColumn('source_label', fn (WalletRefund $r) => $r->source_type.' #'.$r->source_id)
            ->addColumn('amount_fmt', fn (WalletRefund $r) => number_format((float) $r->amount, 2).' '.$r->currency)
            ->addColumn('status_badge', fn (WalletRefund $r) => AdminWalletDataTable::refundStatusInteractive($r))
            ->editColumn('created_at', fn (WalletRefund $r) => $r->created_at?->format('Y-m-d H:i') ?? '')
            ->addColumn('actions', fn (WalletRefund $r) => '<a href="'.route('admin.wallet-refunds.show', $r).'" class="btn btn-sm btn-primary">'.e(__('admin.details')).'</a>')
            ->rawColumns(['customer', 'status_badge', 'actions'])
            ->toJson();
    }

    public function show(WalletRefund $walletRefund): View
    {
        $walletRefund->load(['user', 'reviewer']);

        return view('admin.wallet-refunds.show', ['refund' => $walletRefund]);
    }

    public function updateStatus(Request $request, WalletRefund $walletRefund): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', [
                WalletRefund::STATUS_PENDING,
                WalletRefund::STATUS_APPROVED,
                WalletRefund::STATUS_REJECTED,
            ]),
            'admin_notes' => 'nullable|string|max:10000',
        ]);

        $old = $walletRefund->status;
        $new = $validated['status'];
        $wantsJson = $request->wantsJson() || $request->expectsJson();

        if ($old === $new) {
            if (array_key_exists('admin_notes', $validated)) {
                $walletRefund->admin_notes = $validated['admin_notes'];
                $walletRefund->save();
            }
            $walletRefund->refresh();
            if ($wantsJson) {
                return response()->json([
                    'ok' => true,
                    'message' => __('admin.wallet_refund_status_updated'),
                    'status' => $walletRefund->status,
                ]);
            }

            return redirect()
                ->route('admin.wallet-refunds.show', $walletRefund)
                ->with('success', __('admin.wallet_refund_status_updated'));
        }

        if ($new === WalletRefund::STATUS_APPROVED && $old === WalletRefund::STATUS_PENDING) {
            if (array_key_exists('admin_notes', $validated)) {
                $walletRefund->admin_notes = $validated['admin_notes'];
                $walletRefund->save();
            }
            try {
                $this->refundProcessor->approveAndCredit($walletRefund, (int) auth('admin')->id());
            } catch (\Throwable $e) {
                if ($wantsJson) {
                    return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
                }

                return redirect()
                    ->route('admin.wallet-refunds.show', $walletRefund)
                    ->with('error', $e->getMessage());
            }
            $this->notifier->notifyApproved($walletRefund->fresh());
            $walletRefund->refresh();

            if ($wantsJson) {
                return response()->json([
                    'ok' => true,
                    'message' => __('admin.wallet_refund_status_updated'),
                    'status' => $walletRefund->status,
                ]);
            }

            return redirect()
                ->route('admin.wallet-refunds.show', $walletRefund)
                ->with('success', __('admin.wallet_refund_status_updated'));
        }

        if ($new === WalletRefund::STATUS_REJECTED && $old === WalletRefund::STATUS_PENDING) {
            $walletRefund->status = WalletRefund::STATUS_REJECTED;
            if (array_key_exists('admin_notes', $validated)) {
                $walletRefund->admin_notes = $validated['admin_notes'];
            }
            $walletRefund->reviewed_by = auth('admin')->id();
            $walletRefund->reviewed_at = now();
            $walletRefund->save();
            $this->notifier->notifyRejected($walletRefund->fresh());
            $walletRefund->refresh();

            if ($wantsJson) {
                return response()->json([
                    'ok' => true,
                    'message' => __('admin.wallet_refund_status_updated'),
                    'status' => $walletRefund->status,
                ]);
            }

            return redirect()
                ->route('admin.wallet-refunds.show', $walletRefund)
                ->with('success', __('admin.wallet_refund_status_updated'));
        }

        if ($new === WalletRefund::STATUS_PENDING) {
            if ($wantsJson) {
                return response()->json(['ok' => false, 'message' => 'Cannot revert to pending.'], 422);
            }

            return redirect()
                ->route('admin.wallet-refunds.show', $walletRefund)
                ->with('error', 'Cannot revert to pending.');
        }

        if ($wantsJson) {
            return response()->json(['ok' => false, 'message' => 'Invalid status change.'], 422);
        }

        return redirect()
            ->route('admin.wallet-refunds.show', $walletRefund)
            ->with('error', 'Invalid status change.');
    }
}
