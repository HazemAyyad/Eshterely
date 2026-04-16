<?php

namespace App\Support;

use App\Models\PurchaseAssistantRequest;

/**
 * HTML helpers for admin Purchase Assistant DataTables (badges, link column, inline status).
 */
final class AdminPurchaseAssistantDataTable
{
    /**
     * Solid Bootstrap badge class (matches admin/wallet-refunds interactive style).
     */
    public static function statusButtonClass(PurchaseAssistantRequest $r): string
    {
        return match ($r->status) {
            PurchaseAssistantRequest::STATUS_SUBMITTED => 'secondary',
            PurchaseAssistantRequest::STATUS_UNDER_REVIEW => 'info',
            PurchaseAssistantRequest::STATUS_AWAITING_CUSTOMER_PAYMENT,
            PurchaseAssistantRequest::STATUS_PAYMENT_UNDER_REVIEW => 'warning',
            PurchaseAssistantRequest::STATUS_PAID,
            PurchaseAssistantRequest::STATUS_COMPLETED => 'success',
            PurchaseAssistantRequest::STATUS_REJECTED,
            PurchaseAssistantRequest::STATUS_CANCELLED => 'danger',
            default => 'primary',
        };
    }

    /**
     * Clickable status badge opening the shared wallet inline-status modal (same UX as wallet-refunds).
     */
    public static function statusInteractive(PurchaseAssistantRequest $r): string
    {
        $label = e($r->status);
        $class = self::statusButtonClass($r);
        $url = e(route('admin.purchase-assistant.update-status', $r));
        $opts = e(json_encode(PurchaseAssistantRequest::statuses()) ?: '[]');

        return '<button type="button" class="badge bg-'.$class.' border-0 js-wallet-inline-status text-start" style="cursor:pointer;max-width:100%;white-space:normal;" data-url="'.$url.'" data-options="'.$opts.'" data-current="'.$label.'">'.$label.'</button>';
    }

    public static function statusBadge(PurchaseAssistantRequest $r): string
    {
        $s = $r->status;
        $class = match ($s) {
            PurchaseAssistantRequest::STATUS_SUBMITTED => 'bg-label-secondary',
            PurchaseAssistantRequest::STATUS_UNDER_REVIEW => 'bg-label-info',
            PurchaseAssistantRequest::STATUS_AWAITING_CUSTOMER_PAYMENT => 'bg-label-warning',
            PurchaseAssistantRequest::STATUS_PAYMENT_UNDER_REVIEW => 'bg-label-warning',
            PurchaseAssistantRequest::STATUS_PAID => 'bg-label-success',
            PurchaseAssistantRequest::STATUS_COMPLETED => 'bg-label-success',
            PurchaseAssistantRequest::STATUS_REJECTED,
            PurchaseAssistantRequest::STATUS_CANCELLED => 'bg-label-danger',
            PurchaseAssistantRequest::STATUS_PURCHASING,
            PurchaseAssistantRequest::STATUS_PURCHASED,
            PurchaseAssistantRequest::STATUS_IN_TRANSIT_TO_WAREHOUSE,
            PurchaseAssistantRequest::STATUS_RECEIVED_AT_WAREHOUSE => 'bg-label-primary',
            default => 'bg-label-primary',
        };

        return '<span class="badge '.$class.'">'.e($s).'</span>';
    }

    public static function sourceLinkButton(PurchaseAssistantRequest $r): string
    {
        $url = $r->source_url;
        if ($url === '' || $url === null) {
            return '<span class="text-muted">—</span>';
        }

        return '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-icon btn-text-secondary" title="Open product URL" aria-label="Open product URL">'
            .'<i class="icon-base ti tabler-external-link icon-18px"></i>'
            .'</a>';
    }
}
