<?php

namespace App\Services;

use App\Models\ImportedProduct;
use App\Models\Order;

/**
 * Foundation for creating draft orders from imported product snapshots.
 * Full implementation (cart review + order creation + payment-safe finalization) is planned for the next task.
 */
class DraftOrderService
{
    /**
     * Create a draft order from a confirmed imported product snapshot.
     * Uses snapshot data only; does not recalculate pricing or shipping.
     *
     * Next task: implement full flow (address, wallet, payment intent, status draft → placed).
     *
     * @return Order|null Draft order, or null if not yet implemented / validation fails
     */
    public function createFromImportedProduct(ImportedProduct $imported): ?Order
    {
        if ($imported->status !== ImportedProduct::STATUS_DRAFT && $imported->status !== ImportedProduct::STATUS_ADDED_TO_CART) {
            return null;
        }

        // Placeholder: next task will create Order with status 'draft', OrderShipment, OrderLineItem
        // from $imported->final_pricing_snapshot, shipping_quote_snapshot, and product fields.
        return null;
    }
}
