<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SavedPaymentMethod;
use App\Services\Wallet\StripeSavedCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class SavedPaymentMethodsController extends Controller
{
    public function __construct(
        protected StripeSavedCardService $stripeSavedCardService
    ) {}

    public function index(): View
    {
        return view('admin.saved-payment-methods.index');
    }

    public function data()
    {
        $query = SavedPaymentMethod::query()->with('user')->orderByDesc('id');

        return DataTables::eloquent($query)
            ->addColumn('user_contact', fn (SavedPaymentMethod $c) => $c->user?->email ?? $c->user?->phone ?? 'User #'.$c->user_id)
            ->addColumn('card_label', fn (SavedPaymentMethod $c) => ($c->brand ?: 'Card').' •••• '.($c->last4 ?? '????'))
            ->editColumn('created_at', fn (SavedPaymentMethod $c) => $c->created_at?->format('Y-m-d H:i') ?? '')
            ->addColumn('actions', function (SavedPaymentMethod $c) {
                $url = route('admin.users.show', $c->user_id);

                return '<a href="'.$url.'" class="btn btn-sm btn-outline-secondary">'.e(__('admin.user')).'</a> '
                    .'<form action="'.route('admin.saved-payment-methods.disable', $c).'" method="POST" class="d-inline" onsubmit="return confirm(\'Disable this card?\');">'
                    .'<input type="hidden" name="_token" value="'.csrf_token().'">'
                    .'<input type="hidden" name="_method" value="PATCH">'
                    .'<button type="submit" class="btn btn-sm btn-warning" '.($c->verification_status === SavedPaymentMethod::STATUS_DISABLED ? 'disabled' : '').'>Disable</button></form>';
            })
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function disable(SavedPaymentMethod $savedPaymentMethod): RedirectResponse
    {
        if ($savedPaymentMethod->verification_status === SavedPaymentMethod::STATUS_DISABLED) {
            return redirect()->back()->with('error', 'Already disabled.');
        }

        try {
            $this->stripeSavedCardService->detachPaymentMethod($savedPaymentMethod);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $savedPaymentMethod->update(['verification_status' => SavedPaymentMethod::STATUS_DISABLED]);

        return redirect()->back()->with('success', __('admin.saved_payment_method_disabled'));
    }
}
