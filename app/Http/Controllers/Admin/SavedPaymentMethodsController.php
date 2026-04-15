<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SavedPaymentMethod;
use App\Services\Wallet\StripeSavedCardService;
use App\Support\AdminUserDisplay;
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
            ->addColumn('customer', function (SavedPaymentMethod $c) {
                $u = $c->user;
                if (! $u) {
                    return '—';
                }
                $name = e(AdminUserDisplay::primaryName($u));
                $phone = $u->phone ? '<div class="text-muted small">'.e($u->phone).'</div>' : '';
                $email = $u->email ? '<div class="text-muted small">'.e($u->email).'</div>' : '';

                return '<div><a href="'.route('admin.users.show', $u).'" class="fw-semibold">'.$name.'</a></div>'.$phone.$email;
            })
            ->addColumn('card_label', fn (SavedPaymentMethod $c) => ($c->brand ?: 'Card').' •••• '.($c->last4 ?? '????'))
            ->addColumn('status_badge', function (SavedPaymentMethod $c) {
                $raw = $c->verification_status;

                return match ($raw) {
                    SavedPaymentMethod::STATUS_VERIFIED => '<span class="badge bg-success">verified</span>',
                    SavedPaymentMethod::STATUS_PENDING => '<span class="badge bg-warning text-dark">pending_verification</span>',
                    SavedPaymentMethod::STATUS_BLOCKED => '<span class="badge bg-danger">blocked</span>',
                    SavedPaymentMethod::STATUS_FAILED => '<span class="badge bg-danger">failed_verification</span>',
                    SavedPaymentMethod::STATUS_DISABLED => '<span class="badge bg-secondary">disabled</span>',
                    default => '<span class="badge bg-secondary">'.e($raw).'</span>',
                };
            })
            ->addColumn('failed_attempts', fn (SavedPaymentMethod $c) => (string) (int) $c->verification_attempts)
            ->addColumn('blocked_at_fmt', fn (SavedPaymentMethod $c) => $c->blocked_at?->format('Y-m-d H:i') ?? '—')
            ->editColumn('created_at', fn (SavedPaymentMethod $c) => $c->created_at?->format('Y-m-d H:i') ?? '')
            ->addColumn('actions', function (SavedPaymentMethod $c) {
                $userUrl = route('admin.users.show', $c->user_id);
                $btns = '<div class="d-flex flex-wrap gap-1">'
                    .'<a href="'.$userUrl.'" class="btn btn-sm btn-outline-secondary">'.e(__('admin.user')).'</a>';

                if ($c->verification_status === SavedPaymentMethod::STATUS_BLOCKED) {
                    $btns .= '<form action="'.route('admin.saved-payment-methods.unblock', $c).'" method="POST" class="d-inline" onsubmit="return confirm(\'Unblock this card and reset failed verification attempts? The customer can try entering the verification amount again.\');">'
                        .'<input type="hidden" name="_token" value="'.csrf_token().'">'
                        .'<input type="hidden" name="_method" value="PATCH">'
                        .'<button type="submit" class="btn btn-sm btn-success">Unblock / reset</button></form>';
                }

                $btns .= '<form action="'.route('admin.saved-payment-methods.disable', $c).'" method="POST" class="d-inline" onsubmit="return confirm(\'Disable this card? It will be detached in Stripe.\');">'
                    .'<input type="hidden" name="_token" value="'.csrf_token().'">'
                    .'<input type="hidden" name="_method" value="PATCH">'
                    .'<button type="submit" class="btn btn-sm btn-warning" '
                    .($c->verification_status === SavedPaymentMethod::STATUS_DISABLED ? 'disabled' : '').'>Disable</button></form>'
                    .'</div>';

                return $btns;
            })
            ->rawColumns(['customer', 'status_badge', 'actions'])
            ->toJson();
    }

    public function unblock(SavedPaymentMethod $savedPaymentMethod): RedirectResponse
    {
        if ($savedPaymentMethod->verification_status !== SavedPaymentMethod::STATUS_BLOCKED) {
            return redirect()->back()->with('error', 'Only blocked cards can be unblocked from this action.');
        }

        $savedPaymentMethod->update([
            'verification_status' => SavedPaymentMethod::STATUS_PENDING,
            'verification_attempts' => 0,
            'blocked_at' => null,
            'blocked_reason' => null,
        ]);

        return redirect()->back()->with('success', 'Card unblocked. Failed verification attempts were reset.');
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
