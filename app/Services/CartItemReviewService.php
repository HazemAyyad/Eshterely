<?php

namespace App\Services;

use App\Models\ImportedProduct;

/**
 * Rule-based determination of whether an imported product cart item requires operational review.
 * Used when adding an imported product to cart and when exposing cart API responses.
 */
class CartItemReviewService
{
    /**
     * Determine if an imported product (or its snapshot data) requires operational review.
     * Rule-based; no scoring.
     *
     * needs_review = true if:
     * - estimated shipping/pricing = true
     * - missing_fields not empty
     * - fallback defaults were used (in notes)
     * - carrier auto-selection occurred
     * - pricing snapshot incomplete (e.g. missing final_total)
     */
    public function importedProductNeedsReview(ImportedProduct $imported): bool
    {
        if ($imported->estimated) {
            return true;
        }

        $missingFields = $imported->missing_fields;
        if (is_array($missingFields) && count($missingFields) > 0) {
            return true;
        }

        $shippingSnapshot = $imported->shipping_quote_snapshot ?? [];
        if ($this->usedFallbackDefaults($shippingSnapshot)) {
            return true;
        }

        if ($this->carrierAutoSelected($imported->carrier, $shippingSnapshot)) {
            return true;
        }

        if ($this->pricingSnapshotIncomplete($imported->final_pricing_snapshot ?? [])) {
            return true;
        }

        return false;
    }

    /**
     * Determine needs_review from cart item snapshot data (e.g. when building API response).
     *
     * @param array<string, mixed>|null $shippingSnapshot
     * @param array<string, mixed>|null $pricingSnapshot
     */
    public function snapshotNeedsReview(
        bool $estimated,
        ?array $missingFields,
        ?string $carrier,
        $shippingSnapshot,
        $pricingSnapshot
    ): bool {
        if ($estimated) {
            return true;
        }
        if (is_array($missingFields) && count($missingFields) > 0) {
            return true;
        }
        if ($this->usedFallbackDefaults($shippingSnapshot ?? [])) {
            return true;
        }
        if ($this->carrierAutoSelected($carrier, $shippingSnapshot ?? [])) {
            return true;
        }
        if ($this->pricingSnapshotIncomplete($pricingSnapshot ?? [])) {
            return true;
        }
        return false;
    }

    private function usedFallbackDefaults(array $shippingSnapshot): bool
    {
        $notes = $shippingSnapshot['notes'] ?? [];
        if (! is_array($notes)) {
            return false;
        }
        $fallbackPhrases = ['fallback', 'default', 'estimated'];
        foreach ($notes as $note) {
            $lower = is_string($note) ? strtolower($note) : '';
            foreach ($fallbackPhrases as $phrase) {
                if (str_contains($lower, $phrase)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function carrierAutoSelected(?string $carrier, array $shippingSnapshot): bool
    {
        $snapshotCarrier = $shippingSnapshot['carrier'] ?? null;
        $carrierToCheck = $carrier ?? $snapshotCarrier;
        return $carrierToCheck === 'auto' || $carrierToCheck === 'auto_selected';
    }

    /**
     * @param array<string, mixed> $pricingSnapshot
     */
    private function pricingSnapshotIncomplete(array $pricingSnapshot): bool
    {
        $finalTotal = $pricingSnapshot['final_total'] ?? null;
        if ($finalTotal === null || $finalTotal === '') {
            return true;
        }
        return false;
    }
}
