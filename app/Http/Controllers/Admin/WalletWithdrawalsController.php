<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletWithdrawal;
use App\Services\Wallet\WalletWithdrawalNotifier;
use App\Services\Wallet\WalletWithdrawalProcessor;
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
            ->addColumn('user_contact', fn (WalletWithdrawal $w) => $w->user?->email ?? $w->user?->phone ?? 'User #'.$w->user_id)
            ->addColumn('amount_fmt', fn (WalletWithdrawal $w) => number_format((float) $w->amount, 2).' (net '.number_format((float) $w->net_amount, 2).')')
            ->editColumn('created_at', fn (WalletWithdrawal $w) => $w->created_at?->format('Y-m-d H:i') ?? '')
            ->addColumn('actions', fn (WalletWithdrawal $w) => '<a href="'.route('admin.wallet-withdrawals.show', $w).'" class="btn btn-sm btn-primary">'.e(__('admin.details')).'</a>')
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function show(WalletWithdrawal $walletWithdrawal): View
    {
        $walletWithdrawal->load(['user', 'reviewer']);

        return view('admin.wallet-withdrawals.show', ['withdrawal' => $walletWithdrawal]);
    }

    public function updateStatus(Request $request, WalletWithdrawal $walletWithdrawal): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', WalletWithdrawal::statuses()),
            'admin_notes' => 'nullable|string|max:10000',
            'transfer_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $old = $walletWithdrawal->status;
        $new = $validated['status'];

        if ($new === WalletWithdrawal::STATUS_TRANSFERRED) {
            if ($old === WalletWithdrawal::STATUS_TRANSFERRED) {
                return redirect()->back()->with('error', 'Already transferred.');
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
                return redirect()->back()->with('error', 'Transfer proof file is required to mark as transferred.');
            }

            try {
                $this->withdrawalProcessor->markTransferredAndDebit($walletWithdrawal, $path);
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', $e->getMessage());
            }

            $this->notifier->notifyTransferred($walletWithdrawal->fresh());

            return redirect()
                ->route('admin.wallet-withdrawals.show', $walletWithdrawal)
                ->with('success', __('admin.wallet_withdrawal_status_updated'));
        }

        if ($old === WalletWithdrawal::STATUS_TRANSFERRED || $old === WalletWithdrawal::STATUS_REJECTED) {
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

        return redirect()
            ->route('admin.wallet-withdrawals.show', $walletWithdrawal)
            ->with('success', __('admin.wallet_withdrawal_status_updated'));
    }
}
