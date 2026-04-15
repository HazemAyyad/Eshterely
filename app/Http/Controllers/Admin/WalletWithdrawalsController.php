<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletWithdrawal;
use App\Services\Wallet\WalletWithdrawalNotifier;
use App\Services\Wallet\WalletWithdrawalProcessor;
use App\Support\AdminWalletDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class WalletWithdrawalsController extends Controller
{
    public function __construct(
        protected WalletWithdrawalNotifier $notifier,
        protected WalletWithdrawalProcessor $withdrawalProcessor
    ) {}

    public function index(): View
    {
        return view('admin.wallet-withdrawals.index');
    }

    public function data()
    {
        $query = WalletWithdrawal::query()->with('user')->orderByDesc('id');

        return DataTables::eloquent($query)
            ->addColumn('customer', fn (WalletWithdrawal $w) => AdminWalletDataTable::customerCell($w->user))
            ->addColumn('amount_fmt', fn (WalletWithdrawal $w) => number_format((float) $w->amount, 2).' (net '.number_format((float) $w->net_amount, 2).')')
            ->addColumn('status_badge', fn (WalletWithdrawal $w) => AdminWalletDataTable::withdrawalStatusInteractive($w))
            ->editColumn('created_at', fn (WalletWithdrawal $w) => $w->created_at?->format('Y-m-d H:i') ?? '')
            ->addColumn('actions', fn (WalletWithdrawal $w) => '<a href="'.route('admin.wallet-withdrawals.show', $w).'" class="btn btn-sm btn-primary">'.e(__('admin.details')).'</a>')
            ->rawColumns(['customer', 'status_badge', 'actions'])
            ->toJson();
    }

    public function show(WalletWithdrawal $walletWithdrawal): View
    {
        $walletWithdrawal->load(['user', 'reviewer']);

        return view('admin.wallet-withdrawals.show', ['withdrawal' => $walletWithdrawal]);
    }

    public function updateStatus(Request $request, WalletWithdrawal $walletWithdrawal): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', WalletWithdrawal::statuses()),
            'admin_notes' => 'nullable|string|max:10000',
            'transfer_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $old = $walletWithdrawal->status;
        $new = $validated['status'];
        $wantsJson = $request->wantsJson() || $request->expectsJson();

        if ($old === $new) {
            if (array_key_exists('admin_notes', $validated)) {
                $walletWithdrawal->admin_notes = $validated['admin_notes'];
                $walletWithdrawal->save();
            }
            $walletWithdrawal->refresh();
            if ($wantsJson) {
                return response()->json([
                    'ok' => true,
                    'message' => __('admin.wallet_withdrawal_status_updated'),
                    'status' => $walletWithdrawal->status,
                ]);
            }

            return redirect()
                ->route('admin.wallet-withdrawals.show', $walletWithdrawal)
                ->with('success', __('admin.wallet_withdrawal_status_updated'));
        }

        if ($new === WalletWithdrawal::STATUS_TRANSFERRED) {
            if ($old === WalletWithdrawal::STATUS_TRANSFERRED) {
                if ($wantsJson) {
                    return response()->json(['ok' => false, 'message' => 'Already transferred.'], 422);
                }

                return redirect()->back()->with('error', 'Already transferred.');
            }
            if ($wantsJson && ! $request->hasFile('transfer_proof') && empty($walletWithdrawal->transfer_proof)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Transfer proof is required to mark as transferred. Open the detail page to upload a file.',
                ], 422);
            }
            if (array_key_exists('admin_notes', $validated)) {
                $walletWithdrawal->admin_notes = $validated['admin_notes'];
                $walletWithdrawal->save();
            }
            $path = null;
            if ($request->hasFile('transfer_proof')) {
                $path = $request->file('transfer_proof')->store('wallet-withdrawals/'.$walletWithdrawal->id, 'public');
            } elseif (! empty($walletWithdrawal->transfer_proof)) {
                $path = $walletWithdrawal->transfer_proof;
            }
            if ($path === null || trim((string) $path) === '') {
                if ($wantsJson) {
                    return response()->json(['ok' => false, 'message' => 'Transfer proof file is required to mark as transferred.'], 422);
                }

                return redirect()->back()->with('error', 'Transfer proof file is required to mark as transferred.');
            }

            try {
                $this->withdrawalProcessor->markTransferredAndDebit($walletWithdrawal, $path);
            } catch (\Throwable $e) {
                if ($wantsJson) {
                    return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
                }

                return redirect()->back()->with('error', $e->getMessage());
            }

            $this->notifier->notifyTransferred($walletWithdrawal->fresh());
            $walletWithdrawal->refresh();

            if ($wantsJson) {
                return response()->json([
                    'ok' => true,
                    'message' => __('admin.wallet_withdrawal_status_updated'),
                    'status' => $walletWithdrawal->status,
                ]);
            }

            return redirect()
                ->route('admin.wallet-withdrawals.show', $walletWithdrawal)
                ->with('success', __('admin.wallet_withdrawal_status_updated'));
        }

        if ($old === WalletWithdrawal::STATUS_TRANSFERRED || $old === WalletWithdrawal::STATUS_REJECTED) {
            if ($wantsJson) {
                return response()->json(['ok' => false, 'message' => 'Cannot change status.'], 422);
            }

            return redirect()->back()->with('error', 'Cannot change status.');
        }

        $walletWithdrawal->status = $new;
        if (array_key_exists('admin_notes', $validated)) {
            $walletWithdrawal->admin_notes = $validated['admin_notes'];
        }
        $walletWithdrawal->reviewed_by = auth('admin')->id();
        if ($walletWithdrawal->reviewed_at === null) {
            $walletWithdrawal->reviewed_at = now();
        }
        $walletWithdrawal->save();
        $fresh = $walletWithdrawal->fresh();

        if ($old !== $new) {
            if ($new === WalletWithdrawal::STATUS_APPROVED) {
                $this->notifier->notifyApproved($fresh);
            } elseif ($new === WalletWithdrawal::STATUS_REJECTED) {
                $this->notifier->notifyRejected($fresh);
            }
        }

        if ($wantsJson) {
            return response()->json([
                'ok' => true,
                'message' => __('admin.wallet_withdrawal_status_updated'),
                'status' => $fresh->status,
            ]);
        }

        return redirect()
            ->route('admin.wallet-withdrawals.show', $walletWithdrawal)
            ->with('success', __('admin.wallet_withdrawal_status_updated'));
    }
}
