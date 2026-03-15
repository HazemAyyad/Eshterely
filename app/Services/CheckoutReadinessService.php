<?php

namespace App\Services;

use App\Models\DraftOrder;
use App\Models\DraftOrderItem;

/**
 * Evaluates whether a draft order is ready for checkout.
 * Does not modify the draft; used by checkout endpoint to allow or block order creation.
 *
 * Future granular review states (DraftOrder::REVIEW_STATE_*): when implemented,
 * add checks here for needs_admin_review, needs_reprice, needs_shipping_completion
 * so they become blocking_issues until resolved.
 */
class CheckoutReadinessService
{
    /**
     * Keys in draft_order.review_state that, when true, will block checkout once implemented.
     * Currently only needs_review is used; extend this array when adding granular states.
     *
     * @var array<int, string>
     */
    private const BLOCKING_REVIEW_STATE_KEYS = [
        'needs_review',
        // 'needs_admin_review' => DraftOrder::REVIEW_STATE_NEEDS_ADMIN_REVIEW,
        // 'needs_reprice' => DraftOrder::REVIEW_STATE_NEEDS_REPRICE,
        // 'needs_shipping_completion' => DraftOrder::REVIEW_STATE_NEEDS_SHIPPING_COMPLETION,
    ];
    /**
     * Evaluate readiness for checkout.
     *
     * @return array{
     *   ready_for_checkout: bool,
     *   needs_review: bool,
     *   warnings: array<int, string>,
     *   blocking_issues: array<int, string>
     * }
     */
    public function evaluate(DraftOrder $draftOrder): array
    {
        $draftOrder->loadMissing('items');
        $blocking = [];
        $warnings = [];

        if ($draftOrder->needs_review) {
            $blocking[] = 'Draft order requires review before checkout.';
        }

        $anyEstimated = false;
        foreach ($draftOrder->items as $item) {
            if ($item->estimated) {
                $anyEstimated = true;
                break;
            }
        }
        if ($anyEstimated) {
            $blocking[] = 'One or more items have estimated pricing or shipping; resolve before checkout.';
        }

        foreach ($draftOrder->items as $item) {
            $missing = $item->missing_fields ?? [];
            if ($missing !== [] && $missing !== null) {
                $blocking[] = 'One or more items have missing fields (e.g. weight/dimensions); complete before checkout.';
                break;
            }
        }

        foreach ($draftOrder->items as $item) {
            if ($this->isShippingCarrierUnresolved($item)) {
                $blocking[] = 'Shipping carrier is unresolved for one or more items.';
                break;
            }
        }

        if ($this->isPricingIncomplete($draftOrder)) {
            $blocking[] = 'Pricing is incomplete; draft order or items lack valid totals.';
        }

        if ($draftOrder->items->isEmpty()) {
            $blocking[] = 'Draft order has no items.';
        }

        if ($anyEstimated && ! in_array('One or more items have estimated pricing or shipping; resolve before checkout.', $blocking, true)) {
            $warnings[] = 'Some items use estimated pricing or shipping.';
        }
        if ($draftOrder->review_state !== null && $draftOrder->review_state !== []) {
            $warnings[] = 'Draft has review state flags; confirm they are resolved.';
        }

        $blocking = array_values(array_unique($blocking));
        $warnings = array_values(array_unique($warnings));
        $ready = $blocking === [];

        return [
            'ready_for_checkout' => $ready,
            'needs_review' => (bool) $draftOrder->needs_review,
            'warnings' => $warnings,
            'blocking_issues' => $blocking,
        ];
    }

    private function isShippingCarrierUnresolved(DraftOrderItem $item): bool
    {
        $carrier = $item->review_metadata['carrier'] ?? $item->shipping_snapshot['carrier'] ?? null;
        if ($carrier === null || $carrier === '') {
            return true;
        }
        $c = strtolower((string) $carrier);
        return $c === 'auto' || $c === 'auto_selected';
    }

    private function isPricingIncomplete(DraftOrder $draftOrder): bool
    {
        $total = $draftOrder->final_total_snapshot ?? 0;
        if ($total === null || (float) $total <= 0) {
            return true;
        }
        foreach ($draftOrder->items as $item) {
            $ps = $item->pricing_snapshot ?? [];
            $lineTotal = $ps['final_total'] ?? $ps['subtotal'] ?? null;
            if ($lineTotal === null || (float) $lineTotal <= 0) {
                return true;
            }
        }
        return false;
    }
}
