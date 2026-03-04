<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class WalletController extends Controller
{
    public function index(): View
    {
        return view('admin.wallets.index');
    }

    public function data()
    {
        $query = Wallet::with('user')->orderBy('available_balance', 'desc');

        return DataTables::eloquent($query)
            ->addColumn('user_contact', fn (Wallet $w) => $w->user?->phone ?? $w->user?->email ?? 'User #' . $w->user_id)
            ->editColumn('available_balance', fn (Wallet $w) => number_format((float) $w->available_balance, 2))
            ->editColumn('pending_balance', fn (Wallet $w) => number_format((float) $w->pending_balance, 2))
            ->editColumn('promo_balance', fn (Wallet $w) => number_format((float) $w->promo_balance, 2))
            ->addColumn('actions', fn (Wallet $w) => '<a href="' . route('admin.wallets.show', $w->user_id) . '" class="btn btn-text-secondary rounded-pill waves-effect btn-icon" title="' . __('admin.details') . '"><i class="icon-base ti tabler-eye icon-22px"></i></a>')
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function show(int $user): View
    {
        $userModel = User::findOrFail($user);
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $userModel->id],
            ['available_balance' => 0, 'pending_balance' => 0, 'promo_balance' => 0]
        );
        $wallet->load(['transactions' => fn ($q) => $q->orderBy('created_at', 'desc')->limit(50)]);

        return view('admin.wallets.show', compact('userModel', 'wallet'));
    }
}
