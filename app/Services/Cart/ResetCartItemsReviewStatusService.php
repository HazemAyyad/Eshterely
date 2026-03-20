<?php

namespace App\Services\Cart;

use App\Models\CartItem;

/**
 * When the user's default delivery address changes, imported cart lines must be re-reviewed
 * (shipping destination / quotes may no longer match).
 */
final class ResetCartItemsReviewStatusService
{
    public function __invoke(int $userId): void
    {
        CartItem::query()
            ->where('user_id', $userId)
            ->whereNull('draft_order_id')
            ->update(['review_status' => CartItem::REVIEW_STATUS_PENDING]);
    }
}
