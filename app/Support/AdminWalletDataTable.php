<?php

namespace App\Support;

use App\Models\User;
use App\Models\WalletRefund;
use App\Models\WalletTopupRequest;
use App\Models\WalletWithdrawal;

/**
 * HTML fragments for wallet-related admin DataTables (customer column + interactive status).
 */
class AdminWalletDataTable
{
    public static function customerCell(?User $user): string
    {
        if (! $user) {
            return '—';
        }
        $name = e(AdminUserDisplay::primaryName($user));
        $code = AdminUserDisplay::customerCodeLineHtml($user);
        $phone = $user->phone ? '<div class="text-muted small">'.e($user->phone).'</div>' : '';
        $email = $user->email ? '<div class="text-muted small">'.e($user->email).'</div>' : '';

        return '<div><a href="'.route('admin.users.show', $user).'" class="fw-semibold">'.$name.'</a></div>'.$code.$phone.$email;
    }

    public static function topupStatusInteractive(WalletTopupRequest $r): string
    {
        $class = match ($r->status) {
            WalletTopupRequest::STATUS_APPROVED => 'success',
            WalletTopupRequest::STATUS_REJECTED => 'danger',
            WalletTopupRequest::STATUS_UNDER_REVIEW => 'info',
            default => 'warning',
        };
        $label = e($r->status);
        $finalized = in_array($r->status, [WalletTopupRequest::STATUS_APPROVED, WalletTopupRequest::STATUS_REJECTED], true);
        if ($finalized) {
            return '<span class="badge bg-'.$class.'">'.$label.'</span>';
        }
        $url = e(route('admin.wallet-topup-requests.update-status', $r));
        $opts = e(json_encode([
            WalletTopupRequest::STATUS_PENDING,
            WalletTopupRequest::STATUS_UNDER_REVIEW,
            WalletTopupRequest::STATUS_APPROVED,
            WalletTopupRequest::STATUS_REJECTED,
        ]) ?: '[]');

        return '<button type="button" class="badge bg-'.$class.' border-0 js-wallet-inline-status text-start" style="cursor:pointer" data-url="'.$url.'" data-options="'.$opts.'" data-current="'.$label.'">'.$label.'</button>';
    }

    public static function withdrawalStatusInteractive(WalletWithdrawal $w): string
    {
        $class = match ($w->status) {
            WalletWithdrawal::STATUS_TRANSFERRED => 'success',
            WalletWithdrawal::STATUS_REJECTED => 'danger',
            WalletWithdrawal::STATUS_APPROVED => 'primary',
            WalletWithdrawal::STATUS_UNDER_REVIEW => 'info',
            default => 'warning',
        };
        $label = e($w->status);
        $quick = in_array($w->status, [WalletWithdrawal::STATUS_PENDING, WalletWithdrawal::STATUS_UNDER_REVIEW], true);
        if (! $quick) {
            return '<span class="badge bg-'.$class.'">'.$label.'</span>';
        }
        $options = array_values(array_diff(WalletWithdrawal::statuses(), [WalletWithdrawal::STATUS_TRANSFERRED]));
        $url = e(route('admin.wallet-withdrawals.update-status', $w));
        $opts = e(json_encode($options) ?: '[]');

        return '<button type="button" class="badge bg-'.$class.' border-0 js-wallet-inline-status text-start" style="cursor:pointer" data-url="'.$url.'" data-options="'.$opts.'" data-current="'.$label.'" title="Marking transferred requires proof — use Details">'
            .$label.'</button>';
    }

    public static function refundStatusInteractive(WalletRefund $r): string
    {
        $class = match ($r->status) {
            WalletRefund::STATUS_APPROVED => 'success',
            WalletRefund::STATUS_REJECTED => 'danger',
            default => 'warning',
        };
        $label = e($r->status);
        if ($r->status !== WalletRefund::STATUS_PENDING) {
            return '<span class="badge bg-'.$class.'">'.$label.'</span>';
        }
        $url = e(route('admin.wallet-refunds.update-status', $r));
        $opts = e(json_encode([
            WalletRefund::STATUS_APPROVED,
            WalletRefund::STATUS_REJECTED,
        ]) ?: '[]');

        return '<button type="button" class="badge bg-'.$class.' border-0 js-wallet-inline-status text-start" style="cursor:pointer" data-url="'.$url.'" data-options="'.$opts.'" data-current="'.$label.'">'.$label.'</button>';
    }
}
