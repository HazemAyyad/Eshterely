<?php

namespace App\Services\Cart;

use App\Models\CartItem;
use App\Models\Order;

/**
 * Deletes cart rows that were converted into order line items (by cart_item_id).
 */
class RemoveOrderedCartItemsService
{
    public function __invoke(int $orderId): void
    {
        $order = Order::with('shipments.lineItems')->find($orderId);
        if ($order === null) {
            return;
        }

        $cartItemIds = $order->shipments
            ->flatMap(fn ($s) => $s->lineItems->pluck('cart_item_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($cartItemIds === []) {
            return;
        }

        CartItem::query()
            ->where('user_id', $order->user_id)
            ->whereIn('id', $cartItemIds)
            ->delete();
    }
}
