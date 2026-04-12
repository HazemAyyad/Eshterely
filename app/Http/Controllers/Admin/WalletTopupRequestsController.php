<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletTopupRequest;
use App\Services\Wallet\WalletManualTopupProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class WalletTopupRequestsController extends Controller
{
    public function __construct(
        protected WalletManualTopupProcessor $processor
    ) {}

    public function index(Request $request): View
    {
        return view('admin.wallet-topup-requests.index', [
            'filterStatus' => $request->query('status', ''),
            'filterMethod' => $request->query('method', ''),
        ]);
    }

    public function data(Request $request)
    {
        $query = WalletTopupRequest::query()->with('user')->orderByDesc('id');

        $st = $request->query('status');
        if (is_string($st) && $st !== '') {
            $query->where('status', $st);
        }

        $mt = $request->query('method');
        if (is_string($mt) && $mt !== '') {
            $query->where('method', $mt);
        }

        return DataTables::eloquent($query)
            ->addColumn('user_contact', fn (WalletTopupRequest $r) => $r->user?->email ?? $r->user?->phone ?? 'User #'.$r->user_id)
            ->addColumn('amount_fmt', fn (WalletTopupRequest $r) => number_format((float) $r->amount, 2).' '.$r->currency)
            ->addColumn('method_label', fn (WalletTopupRequest $r) => $r->method === WalletTopupRequest::METHOD_ZELLE ? 'Zelle' : 'Wire')
            ->editColumn('created_at', fn (WalletTopupRequest $r) => $r->created_at?->format('Y-m-d H:i') ?? '')
            ->addColumn('actions', fn (WalletTopupRequest $r) => '<a href="'.route('admin.wallet-topup-requests.show', $r).'" class="btn btn-sm btn-primary">'.e(__('admin.details')).'</a>')
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function show(WalletTopupRequest $walletTopupRequest): View
    {
        $walletTopupRequest->load(['user', 'reviewer']);

        return view('admin.wallet-topup-requests.show', ['req' => $walletTopupRequest]);
    }

    public function updateStatus(Request $request, WalletTopupRequest $walletTopupRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', [
                WalletTopupRequest::STATUS_PENDING,
                WalletTopupRequest::STATUS_UNDER_REVIEW,
                WalletTopupRequest::STATUS_APPROVED,
                WalletTopupRequest::STATUS_REJECTED,
            ]),
            'admin_notes' => 'nullable|string|max:10000',
        ]);

        $old = $walletTopupRequest->status;
        $new = $validated['status'];

        if ($new === WalletTopupRequest::STATUS_APPROVED) {
            if (in_array($old, [WalletTopupRequest::STATUS_APPROVED, WalletTopupRequest::STATUS_REJECTED], true)) {
                return redirect()->back()->with('error', 'Request is already finalized.');
            }
            if (array_key_exists('admin_notes', $validated)) {
                $walletTopupRequest->admin_notes = $validated['admin_notes'];
                $walletTopupRequest->save();
            }
            try {
                $this->processor->approve($walletTopupRequest, (int) auth('admin')->id());
            } catch (\Throwable $e) {
                return redirect()
                    ->route('admin.wallet-topup-requests.show', $walletTopupRequest)
                    ->with('error', $e->getMessage());
            }

            return redirect()
                ->route('admin.wallet-topup-requests.show', $walletTopupRequest)
                ->with('success', __('admin.wallet_topup_status_updated'));
        }

        if ($new === WalletTopupRequest::STATUS_REJECTED) {
            if (in_array($old, [WalletTopupRequest::STATUS_APPROVED, WalletTopupRequest::STATUS_REJECTED], true)) {
                return redirect()->back()->with('error', 'Request is already finalized.');
            }
            try {
                $this->processor->reject(
                    $walletTopupRequest,
                    (int) auth('admin')->id(),
                    $validated['admin_notes'] ?? null
                );
            } catch (\Throwable $e) {
                return redirect()
                    ->route('admin.wallet-topup-requests.show', $walletTopupRequest)
                    ->with('error', $e->getMessage());
            }

            return redirect()
                ->route('admin.wallet-topup-requests.show', $walletTopupRequest)
                ->with('success', __('admin.wallet_topup_status_updated'));
        }

        // pending / under_review: allow moving between non-terminal states.
        if (in_array($old, [WalletTopupRequest::STATUS_APPROVED, WalletTopupRequest::STATUS_REJECTED], true)) {
            return redirect()->back()->with('error', 'Cannot change a finalized request.');
        }

        $walletTopupRequest->status = $new;
        if (array_key_exists('admin_notes', $validated)) {
            $walletTopupRequest->admin_notes = $validated['admin_notes'];
        }
        if ($new === WalletTopupRequest::STATUS_UNDER_REVIEW && $walletTopupRequest->reviewed_at === null) {
            $walletTopupRequest->reviewed_by = auth('admin')->id();
            $walletTopupRequest->reviewed_at = now();
        }
        $walletTopupRequest->save();

        return redirect()
            ->route('admin.wallet-topup-requests.show', $walletTopupRequest)
            ->with('success', __('admin.wallet_topup_status_updated'));
    }
}
