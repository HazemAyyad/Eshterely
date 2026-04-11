<?php

namespace App\Support;

use App\Models\WarehouseReceipt;
use Illuminate\Support\Str;

/**
 * Admin UI helpers for warehouse receipt rows (queue, modals).
 */
class AdminWarehouseReceiptDisplay
{
    public static function queueIntakeSummaryHtml(?WarehouseReceipt $wr): string
    {
        if (! $wr) {
            return '<span class="text-muted small">—</span>';
        }

        $parts = [];
        if ($wr->received_weight !== null) {
            $parts[] = '<div><span class="text-muted">'.e(__('admin.weight_lb')).':</span> '.e((string) $wr->received_weight).'</div>';
        }
        if ($wr->received_length !== null || $wr->received_width !== null || $wr->received_height !== null) {
            $parts[] = '<div><span class="text-muted">'.e(__('admin.dims_lwh')).':</span> '
                .e((string) ($wr->received_length ?? '—')).' × '.e((string) ($wr->received_width ?? '—')).' × '.e((string) ($wr->received_height ?? '—'))
                .' <span class="text-muted">('.e(__('admin.dim_unit_in')).')</span></div>';
        }
        if ($wr->special_handling_type !== null && $wr->special_handling_type !== '') {
            $parts[] = '<div><span class="text-muted">'.e(__('admin.special_handling')).':</span> '.e(Str::limit($wr->special_handling_type, 40)).'</div>';
        }
        if ($wr->additional_fee_amount !== null && (float) $wr->additional_fee_amount > 0) {
            $parts[] = '<div><span class="text-muted">'.e(__('admin.additional_fee')).':</span> '.e(number_format((float) $wr->additional_fee_amount, 2)).'</div>';
        }
        if ($wr->condition_notes !== null && $wr->condition_notes !== '') {
            $parts[] = '<div class="text-truncate" style="max-width:14rem" title="'.e($wr->condition_notes).'"><span class="text-muted">'.e(__('admin.notes')).':</span> '.e(Str::limit($wr->condition_notes, 80)).'</div>';
        }
        $imgN = is_array($wr->images) ? count($wr->images) : 0;
        if ($imgN > 0) {
            $parts[] = '<div class="text-muted">'.e(__('admin.receipt_images_upload')).': '.(int) $imgN.'</div>';
        }

        if ($parts === []) {
            return '<span class="text-muted small">—</span>';
        }

        return '<div class="small text-start">'.implode('', $parts).'</div>';
    }
}
