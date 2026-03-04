<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index');
    }

    public function data(): JsonResponse
    {
        $query = User::query()->orderBy('created_at', 'desc');

        return DataTables::eloquent($query)
            ->addColumn('display_name_col', fn (User $u) => $u->display_name ?? $u->full_name ?? $u->name ?? '-')
            ->editColumn('verified', fn (User $u) => $u->verified ? __('admin.yes') : __('admin.no'))
            ->editColumn('created_at', fn (User $u) => $u->created_at?->format('Y-m-d'))
            ->addColumn('actions', fn (User $u) => '<a href="' . route('admin.users.show', $u) . '" class="btn btn-text-secondary rounded-pill waves-effect btn-icon" title="' . __('admin.show') . '"><i class="icon-base ti tabler-eye icon-22px"></i></a>')
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function show(int $user): View
    {
        $userModel = User::with([
            'addresses' => function ($q) {
                $q->with(['country', 'city'])
                    ->orderByDesc('is_default')
                    ->orderByDesc('id');
            },
        ])->findOrFail($user);

        $settings = DB::table('user_settings')->where('user_id', $userModel->id)->first();
        $notificationPrefs = DB::table('notification_prefs')->where('user_id', $userModel->id)->first();
        $sessions = DB::table('user_sessions')->where('user_id', $userModel->id)->orderBy('last_active_at', 'desc')->get();

        $primaryAddressModel = $userModel->addresses->firstWhere('is_default', true) ?? $userModel->addresses->first();
        $primaryAddressCountry = $primaryAddressModel?->country?->name;
        $primaryAddressIsDefault = (bool) ($primaryAddressModel?->is_default ?? false);
        $primaryAddressIsLocked = (bool) ($primaryAddressModel?->is_locked ?? false);

        $primaryAddressLines = [];
        if ($primaryAddressModel) {
            if (!empty($primaryAddressModel->address_line)) {
                $primaryAddressLines[] = trim((string) $primaryAddressModel->address_line);
            } else {
                foreach ([
                    $primaryAddressModel->area_district,
                    $primaryAddressModel->street_address,
                    $primaryAddressModel->building_villa_suite,
                ] as $part) {
                    if (!empty($part)) {
                        $primaryAddressLines[] = trim((string) $part);
                    }
                }
            }

            $cityCountryLine = trim(implode(', ', array_filter([
                $primaryAddressModel->city?->name,
                $primaryAddressModel->country?->name,
            ], fn ($v) => !empty($v))));
            if ($cityCountryLine !== '') {
                $primaryAddressLines[] = $cityCountryLine;
            }
        }
        $primaryAddressText = implode("\n", $primaryAddressLines);

        $languageLabel = null;
        if ($settings?->language_code) {
            $languageLabel = match ($settings->language_code) {
                'ar' => __('admin.arabic'),
                'en' => __('admin.english'),
                default => strtoupper((string) $settings->language_code),
            };
        }

        $currencySymbol = null;
        if ($settings?->currency_code) {
            $currencySymbol = match (strtoupper((string) $settings->currency_code)) {
                'USD' => '$',
                'EUR' => '€',
                'GBP' => '£',
                'TRY' => '₺',
                'AED' => 'د.إ',
                'SAR' => 'ر.س',
                'JOD' => 'د.ا',
                default => null,
            };
        }

        $notificationCenterSummary = '—';
        if ($notificationPrefs) {
            $enabled = [];
            if ($notificationPrefs->push_enabled ?? false) {
                $enabled[] = 'Push';
            }
            if ($notificationPrefs->email_enabled ?? false) {
                $enabled[] = 'Email';
            }
            if ($notificationPrefs->sms_enabled ?? false) {
                $enabled[] = 'SMS';
            }
            $notificationCenterSummary = $enabled ? implode(' + ', $enabled) : '—';
            if (($notificationPrefs->quiet_hours_enabled ?? false) && ($notificationPrefs->quiet_hours_from ?? null) && ($notificationPrefs->quiet_hours_to ?? null)) {
                $notificationCenterSummary .= ' • Quiet ' . $notificationPrefs->quiet_hours_from . '-' . $notificationPrefs->quiet_hours_to;
            }
        }

        $wallet = Wallet::where('user_id', $userModel->id)->first();
        $walletTransactions = collect();
        if ($wallet) {
            $walletTransactions = WalletTransaction::where('wallet_id', $wallet->id)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();
        }

        $recentOrders = Order::where('user_id', $userModel->id)
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $recentTickets = SupportTicket::where('user_id', $userModel->id)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $favoritesCount = Favorite::where('user_id', $userModel->id)->count();
        $recentFavorites = Favorite::where('user_id', $userModel->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.users.show', [
            'user' => $userModel,
            'settings' => $settings,
            'notificationPrefs' => $notificationPrefs,
            'sessions' => $sessions,
            'primaryAddressText' => $primaryAddressText,
            'primaryAddressCountry' => $primaryAddressCountry,
            'primaryAddressIsDefault' => $primaryAddressIsDefault,
            'primaryAddressIsLocked' => $primaryAddressIsLocked,
            'languageLabel' => $languageLabel,
            'currencySymbol' => $currencySymbol,
            'notificationCenterSummary' => $notificationCenterSummary,
            'wallet' => $wallet,
            'walletTransactions' => $walletTransactions,
            'recentOrders' => $recentOrders,
            'recentTickets' => $recentTickets,
            'favoritesCount' => $favoritesCount,
            'recentFavorites' => $recentFavorites,
        ]);
    }
}
