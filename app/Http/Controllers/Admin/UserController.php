<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Notification;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\CartItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

        // Keep admin display aligned with what mobile app receives from /api/me/settings.
        $languageCode = $this->normalizeLanguageCode($settings?->language_code ?? null);
        $currencyCode = $this->normalizeCurrencyCode($settings?->currency_code ?? null);
        $warehouseId = $this->normalizeWarehouseId($settings?->default_warehouse_id ?? null);
        $warehouseLabel = $settings?->default_warehouse_label ?? null;
        if (($warehouseLabel === null || trim((string) $warehouseLabel) === '') && $warehouseId !== null) {
            $warehouseLabel = DB::table('warehouses')->where('slug', $warehouseId)->value('label');
        }
        $displaySettings = [
            'language_code' => $languageCode,
            'language_label' => $this->languageLabel($languageCode),
            'currency_code' => $currencyCode,
            'currency_symbol' => $this->currencySymbol($currencyCode),
            // Same fallback used by Flutter settings model/provider.
            'default_warehouse_id' => $warehouseId ?? 'delaware_us',
            'default_warehouse_label' => ($warehouseLabel !== null && trim((string) $warehouseLabel) !== '') ? $warehouseLabel : 'Delaware, US',
            'server_region' => $settings?->server_region ?? null,
            'smart_consolidation_enabled' => (bool) ($settings?->smart_consolidation_enabled ?? true),
            'auto_insurance_enabled' => (bool) ($settings?->auto_insurance_enabled ?? false),
        ];

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

        $recentNotifications = Notification::where('user_id', $userModel->id)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();
        $notificationsUnreadCount = Notification::where('user_id', $userModel->id)
            ->where('read', false)
            ->count();
        $notificationsImportantCount = Notification::where('user_id', $userModel->id)
            ->where('important', true)
            ->count();

        $activeCartItems = CartItem::where('user_id', $userModel->id)
            ->whereNull('draft_order_id')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();
        $cartSummary = [
            'total' => CartItem::where('user_id', $userModel->id)->whereNull('draft_order_id')->count(),
            'pending_review' => CartItem::where('user_id', $userModel->id)->whereNull('draft_order_id')->where('review_status', CartItem::REVIEW_STATUS_PENDING)->count(),
            'reviewed' => CartItem::where('user_id', $userModel->id)->whereNull('draft_order_id')->where('review_status', CartItem::REVIEW_STATUS_REVIEWED)->count(),
            'rejected' => CartItem::where('user_id', $userModel->id)->whereNull('draft_order_id')->where('review_status', CartItem::REVIEW_STATUS_REJECTED)->count(),
            'subtotal' => (float) CartItem::where('user_id', $userModel->id)
                ->whereNull('draft_order_id')
                ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as subtotal')
                ->value('subtotal'),
        ];

        return view('admin.users.show', [
            'user' => $userModel,
            'settings' => $settings,
            'notificationPrefs' => $notificationPrefs,
            'sessions' => $sessions,
            'primaryAddressText' => $primaryAddressText,
            'primaryAddressCountry' => $primaryAddressCountry,
            'primaryAddressIsDefault' => $primaryAddressIsDefault,
            'primaryAddressIsLocked' => $primaryAddressIsLocked,
            'displaySettings' => $displaySettings,
            'notificationCenterSummary' => $notificationCenterSummary,
            'wallet' => $wallet,
            'walletTransactions' => $walletTransactions,
            'recentOrders' => $recentOrders,
            'recentTickets' => $recentTickets,
            'favoritesCount' => $favoritesCount,
            'recentFavorites' => $recentFavorites,
            'recentNotifications' => $recentNotifications,
            'notificationsUnreadCount' => $notificationsUnreadCount,
            'notificationsImportantCount' => $notificationsImportantCount,
            'activeCartItems' => $activeCartItems,
            'cartSummary' => $cartSummary,
        ]);
    }

    public function updatePassword(Request $request, int $user): JsonResponse|RedirectResponse
    {
        $userModel = User::query()->findOrFail($user);

        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $userModel->update([
            'password' => $validated['password'],
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('admin.user_password_updated'),
            ]);
        }

        return redirect()
            ->route('admin.users.show', $userModel)
            ->with('success', __('admin.user_password_updated'));
    }

    private function normalizeLanguageCode(?string $code): string
    {
        $c = strtolower(trim((string) $code));

        return in_array($c, ['en', 'ar'], true) ? $c : 'en';
    }

    private function languageLabel(string $code): string
    {
        return $code === 'ar' ? __('admin.arabic') : __('admin.english');
    }

    private function normalizeCurrencyCode(?string $code): string
    {
        $c = strtoupper(trim((string) $code));

        return $c === '' ? 'USD' : $c;
    }

    private function currencySymbol(string $currencyCode): string
    {
        return match ($currencyCode) {
            'USD' => '$',
            'AED' => 'د.إ',
            'SAR' => '﷼',
            'EUR' => '€',
            'GBP' => '£',
            default => '',
        };
    }

    private function normalizeWarehouseId(?string $warehouseId): ?string
    {
        $w = trim((string) $warehouseId);

        return $w === '' ? null : $w;
    }
}
